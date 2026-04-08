# Déploiement sur o2switch

## Prérequis o2switch

- PHP 8.2+ activé via cPanel MultiPHP Manager
- Extensions PHP : mbstring, xml, curl, pdo_mysql, zip, intl, gd
- Base de données MySQL créée dans cPanel

## Étapes

### 1. Upload des fichiers

Via Git (SSH) ou FTP, uploader tout le projet **sauf** :
- `vendor/` (reconstruit sur place)
- `.env` (créé manuellement)
- `node_modules/`
- `database/database.sqlite`

### 2. Configurer le DocumentRoot

Dans cPanel → Domaines → modifier le sous-domaine pour pointer vers :
```
/home/[user]/[dossier-projet]/public
```

### 3. Installer les dépendances

Via SSH :
```bash
cd /home/[user]/[dossier-projet]
composer install --no-dev --optimize-autoloader
```

### 4. Créer le fichier .env

Copier `.env.production.example` vers `.env` et remplir :
```bash
cp .env.production.example .env
nano .env
```

Générer la clé d'application :
```bash
php artisan key:generate
```

### 5. Migrations et données initiales

```bash
php artisan migrate --force
php artisan db:seed --force
```

### 6. Permissions

```bash
chmod -R 755 storage bootstrap/cache
```

### 7. Configurer le cron (cPanel → Tâches Cron)

```
* * * * * /usr/local/bin/php /home/[user]/[dossier-projet]/artisan schedule:run >> /dev/null 2>&1
```

Ceci déclenche la sync HelloAsso toutes les 30 minutes automatiquement.

### 8. Première synchronisation manuelle

```bash
php artisan sync:helloasso
```

### 9. Changer le mot de passe admin

Se connecter sur l'application et changer le mot de passe depuis la page de profil,
ou via tinker :
```bash
php artisan tinker
>>> App\Models\User::where('email', 'admin@crem-poitiers.fr')->first()->update(['password' => bcrypt('nouveau_mot_de_passe')]);
```

## Identifiants par défaut

- Email : `admin@crem-poitiers.fr`
- Mot de passe : `changeme` ← **À CHANGER IMMÉDIATEMENT**
