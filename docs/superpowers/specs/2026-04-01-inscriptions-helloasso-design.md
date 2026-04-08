# Design — Page /inscriptions avec intégration HelloAsso

**Date :** 2026-04-01
**Projet :** projet-eb (Laravel)
**Statut :** Approuvé

---

## Contexte

L'interface web gère le placement des étudiants LAS pour un examen. Jusqu'ici, les inscriptions étaient synchronisées depuis HelloAsso. L'objectif est de remplacer ce flux par une page `/inscriptions` où les LAS s'inscrivent et paient directement depuis l'interface web, HelloAsso servant uniquement de processeur de paiement via widget embarqué.

---

## Architecture générale

Le système s'articule autour de 3 blocs :

```
[Page /inscriptions]          [Admin]                  [HelloAsso]
 Étudiant saisit CREM    →   Upload liste S2 Excel  →  6 widgets embarqués
         ↓                         ↓                        ↓
   Lookup BDD locale          Table tutoring_members    Webhook POST
         ↓                                                   ↓
   Affiche widget                                   Enregistre l'inscription
   HelloAsso correct                                  dans students
```

**Flux étudiant :**
1. Saisit son numéro CREM (ou déclare ne pas en avoir + indique son niveau LAS)
2. Le backend détermine son tarif selon la logique de résolution
3. Le widget HelloAsso du bon formulaire s'affiche en iframe
4. Paiement sur HelloAsso → webhook → inscription enregistrée en BDD

---

## Logique de détermination du tarif

### Règles métier

- Les numéros CREM en `1xxx` = LAS 1, en `9xxx` = LAS 2/3
- Les numéros en `7xxx` = La Rochelle (hors périmètre, erreur affichée)
- La liste tutorat S2 est importée depuis un fichier Excel rechargeable

### Table de résolution

| CREM | Dans liste tutorat S2 | Tarif |
|------|-----------------------|-------|
| `1xxx` | Oui | LAS 1 — Adhérent tutorat |
| `1xxx` | Non | LAS 1 — Adhérent CREM sans tutorat |
| Aucun + déclare LAS 1 | — | LAS 1 — Non-adhérent |
| `9xxx` | Oui | LAS 2/3 — Adhérent tutorat |
| `9xxx` | Non | LAS 2/3 — Adhérent CREM sans tutorat |
| Aucun + déclare LAS 2/3 | — | LAS 2/3 — Non-adhérent |

### Cas d'erreur

- CREM ne commence pas par 1, 7 ou 9 → erreur "numéro CREM invalide"
- CREM en `7xxx` → erreur "ce numéro correspond à un autre établissement"
- Aucun CREM sans sélection de niveau → erreur de validation

---

## Modèle de données

### Nouvelle table `tutoring_members`

```
tutoring_members
├── id               bigint, PK
├── crem_number      string, indexé, unique
├── first_name       string, nullable
├── last_name        string, nullable
├── created_at       timestamp
└── updated_at       timestamp
```

Stratégie d'import : truncate + insert dans une transaction. Pas de merge, pas d'historique. Chaque import remplace intégralement la liste précédente.

### Table `students` — aucune modification de schéma

Le webhook HelloAsso alimentera les mêmes champs qu'aujourd'hui : `helloasso_item_id`, `helloasso_order_id`, `first_name`, `last_name`, `email`, `tier_name`, `crem_number`, `synced_at`.

### Configuration des formulaires HelloAsso

Dans `config/services.php`, section `helloasso.forms` :

```php
'forms' => [
    'las1_adherent'           => env('HA_FORM_LAS1_ADHERENT'),
    'las1_adherent_sans_tuto' => env('HA_FORM_LAS1_SANS_TUTO'),
    'las1_non_adherent'       => env('HA_FORM_LAS1_NON_ADHERENT'),
    'las2_adherent'           => env('HA_FORM_LAS2_ADHERENT'),
    'las2_adherent_sans_tuto' => env('HA_FORM_LAS2_SANS_TUTO'),
    'las2_non_adherent'       => env('HA_FORM_LAS2_NON_ADHERENT'),
],
```

6 variables `.env` à configurer, une par formulaire HelloAsso créé sur la plateforme.

---

## Page `/inscriptions` (publique)

### Étape 1 — Identification du profil

Formulaire POST vers `/inscriptions/check-tier` :

- Champ CREM (optionnel) avec indication "Je n'ai pas de numéro CREM"
- Si pas de CREM : sélection radio LAS 1 / LAS 2/3
- Validation serveur uniquement (pas de lookup AJAX en étape 1)

**Mécanisme de transition :** POST → `InscriptionController@checkTier` → redirect vers `GET /inscriptions` avec les données du tarif résolu en **session flash** (`tier_key`, `tier_label`, `form_slug`). La vue Blade affiche l'étape 2 si ces données sont présentes en session, sinon l'étape 1.

### Étape 2 — Paiement

- Affiche le tarif détecté (lecture seule, non modifiable)
- Charge l'iframe HelloAsso correspondant au bon formulaire
- Le slug du formulaire est injecté côté serveur — le client ne voit pas les autres slugs
- Lien "Recommencer" pour revenir à l'étape 1 (vide la session flash)

### Sécurité

- La route `/inscriptions/check-tier` est protégée par throttle (ex: 10 req/min par IP)
- Le slug HelloAsso affiché dans l'iframe est résolu côté serveur uniquement

---

## Webhook HelloAsso — `/webhooks/helloasso` (POST, public)

### Traitement

1. Vérifier la signature HMAC (`X-HelloAsso-Signature`) — rejeter si invalide
2. Filtrer uniquement les événements `Order` avec statut `Processed`
3. Extraire : prénom, nom, email, numéro CREM (champ personnalisé), slug du formulaire
4. Déduire le `tier_name` à partir du slug du formulaire (mapping inverse de la config)
5. Upsert dans `students` sur `helloasso_item_id`

### Idempotence

L'upsert sur `helloasso_item_id` garantit qu'un webhook reçu plusieurs fois (retentative HelloAsso) n'insère pas de doublon.

---

## Import Excel — `/admin/tutoring-import` (auth requise)

- Accessible uniquement aux utilisateurs connectés (middleware `auth` existant)
- Upload d'un fichier `.xlsx` ou `.xls`
- Parsing via **PhpSpreadsheet** (nouvelle dépendance Composer)
- Format Excel attendu : première ligne = en-têtes. Colonne avec en-tête contenant "CREM" (insensible à la casse) = numéro CREM obligatoire. Colonnes "Prénom" et "Nom" optionnelles si présentes.
- Si aucune colonne CREM détectée → erreur explicite à l'utilisateur
- Truncate + insert atomique dans une transaction DB
- Affiche après import : date, nombre de lignes chargées
- Affiche la date et le nombre de la dernière importation en haut de page

---

## Nouveaux fichiers

| Fichier | Rôle |
|---------|------|
| `app/Models/TutoringMember.php` | Modèle Eloquent |
| `app/Services/TierResolverService.php` | Logique de détermination du tarif |
| `app/Http/Controllers/InscriptionController.php` | Page publique + check tier |
| `app/Http/Controllers/WebhookController.php` | Réception webhook HelloAsso |
| `app/Http/Controllers/TutoringImportController.php` | Upload Excel admin |
| `database/migrations/..._create_tutoring_members_table.php` | Migration |
| `resources/views/public/inscriptions.blade.php` | Vue publique (2 étapes) |
| `resources/views/admin/tutoring-import.blade.php` | Vue admin import |

---

## Nouvelles routes

```php
// Public
Route::get('/inscriptions', [InscriptionController::class, 'index'])->name('inscriptions.index');
Route::post('/inscriptions/check-tier', [InscriptionController::class, 'checkTier'])
    ->name('inscriptions.check-tier')
    ->middleware('throttle:10,1');

// Webhook (public, sans CSRF)
Route::post('/webhooks/helloasso', [WebhookController::class, 'handle'])
    ->name('webhooks.helloasso');

// Admin
Route::middleware('auth')->group(function () {
    Route::get('/admin/tutoring-import', [TutoringImportController::class, 'index'])
        ->name('admin.tutoring-import');
    Route::post('/admin/tutoring-import', [TutoringImportController::class, 'store'])
        ->name('admin.tutoring-import.store');
});
```

Le webhook doit être exclu du middleware CSRF (ajout dans `bootstrap/app.php` ou `VerifyCsrfToken`).

---

## Dépendances à ajouter

```bash
composer require phpoffice/phpspreadsheet
```
