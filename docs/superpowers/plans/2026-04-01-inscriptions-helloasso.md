# Page /inscriptions + HelloAsso — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Créer la page publique `/inscriptions` où les LAS choisissent leur tarif automatiquement et paient via widget HelloAsso embarqué, avec réception des inscriptions par webhook.

**Architecture:** Session flash (POST → redirect → GET) pour la transition entre les 2 étapes ; `TierResolverService` encapsule toute la logique de résolution de tarif ; le webhook HelloAsso fait l'upsert en base sur `helloasso_item_id`.

**Tech Stack:** Laravel 13, PHPUnit, Eloquent, `maatwebsite/excel` (déjà installé), PhpSpreadsheet (transitif), Tailwind CDN, `#CC2929`.

---

## Fichiers créés / modifiés

| Action  | Fichier |
|---------|---------|
| Create  | `database/migrations/2026_04_01_000001_create_tutoring_members_table.php` |
| Create  | `app/Models/TutoringMember.php` |
| Create  | `app/Services/TierResult.php` |
| Create  | `app/Services/TierResolverService.php` |
| Modify  | `config/services.php` |
| Modify  | `.env.example` |
| Create  | `app/Http/Controllers/InscriptionController.php` |
| Create  | `app/Http/Controllers/WebhookController.php` |
| Create  | `app/Http/Controllers/TutoringImportController.php` |
| Modify  | `routes/web.php` |
| Modify  | `bootstrap/app.php` |
| Create  | `resources/views/public/inscriptions.blade.php` |
| Create  | `resources/views/admin/tutoring-import.blade.php` |
| Create  | `tests/Feature/TierResolverServiceTest.php` |
| Create  | `tests/Feature/InscriptionControllerTest.php` |
| Create  | `tests/Feature/WebhookControllerTest.php` |
| Create  | `tests/Feature/TutoringImportControllerTest.php` |

---

## Task 1 — Migration + Modèle TutoringMember

**Files:**
- Create: `database/migrations/2026_04_01_000001_create_tutoring_members_table.php`
- Create: `app/Models/TutoringMember.php`
- Test: `tests/Feature/TierResolverServiceTest.php` (les tests de cette task viennent dans la task 2 — ici on vérifie juste que la migration tourne)

- [ ] **Step 1 : Créer la migration**

```php
<?php
// database/migrations/2026_04_01_000001_create_tutoring_members_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutoring_members', function (Blueprint $table) {
            $table->id();
            $table->string('crem_number', 10)->unique();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutoring_members');
    }
};
```

- [ ] **Step 2 : Créer le modèle**

```php
<?php
// app/Models/TutoringMember.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutoringMember extends Model
{
    protected $fillable = ['crem_number', 'first_name', 'last_name'];
}
```

- [ ] **Step 3 : Lancer la migration**

```bash
php artisan migrate
```

Expected output : `... create_tutoring_members_table ........ 3ms DONE`

- [ ] **Step 4 : Commit**

```bash
git add database/migrations/2026_04_01_000001_create_tutoring_members_table.php app/Models/TutoringMember.php
git commit -m "feat: add tutoring_members table and model"
```

---

## Task 2 — TierResult DTO + TierResolverService (TDD)

**Files:**
- Create: `app/Services/TierResult.php`
- Create: `app/Services/TierResolverService.php`
- Test: `tests/Feature/TierResolverServiceTest.php`

Cette tâche couvre 9 cas de test. La logique est dans `TierResolverService::resolve(?string $cremNumber, ?string $lasLevel)`. `TierResult` est un readonly value object.

- [ ] **Step 1 : Écrire les 9 tests (ils doivent tous échouer)**

```php
<?php
// tests/Feature/TierResolverServiceTest.php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TutoringMember;
use App\Services\TierResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TierResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private TierResolverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TierResolverService();
    }

    // --- CREM 1xxx ---

    public function test_crem_1xxx_in_tutorat_returns_las1_adherent(): void
    {
        TutoringMember::create(['crem_number' => '10001']);

        $result = $this->service->resolve('10001', null);

        $this->assertSame('las1_adherent', $result->tierKey);
        $this->assertSame('LAS 1 - ADHERENT', $result->tierLabel);
    }

    public function test_crem_1xxx_not_in_tutorat_returns_las1_sans_tuto(): void
    {
        $result = $this->service->resolve('10002', null);

        $this->assertSame('las1_adherent_sans_tuto', $result->tierKey);
        $this->assertSame('LAS 1 - ADHERENT CREM SANS TUTORAT', $result->tierLabel);
    }

    // --- CREM 9xxx ---

    public function test_crem_9xxx_in_tutorat_returns_las2_adherent(): void
    {
        TutoringMember::create(['crem_number' => '90001']);

        $result = $this->service->resolve('90001', null);

        $this->assertSame('las2_adherent', $result->tierKey);
        $this->assertSame('LAS 2/3 - ADHERENT', $result->tierLabel);
    }

    public function test_crem_9xxx_not_in_tutorat_returns_las2_sans_tuto(): void
    {
        $result = $this->service->resolve('90002', null);

        $this->assertSame('las2_adherent_sans_tuto', $result->tierKey);
        $this->assertSame('LAS 2/3 - ADHERENT CREM SANS TUTORAT', $result->tierLabel);
    }

    // --- Sans CREM ---

    public function test_no_crem_las1_returns_las1_non_adherent(): void
    {
        $result = $this->service->resolve(null, 'las1');

        $this->assertSame('las1_non_adherent', $result->tierKey);
        $this->assertSame('LAS 1 - NON-ADHERENT', $result->tierLabel);
    }

    public function test_no_crem_las2_returns_las2_non_adherent(): void
    {
        $result = $this->service->resolve(null, 'las2');

        $this->assertSame('las2_non_adherent', $result->tierKey);
        $this->assertSame('LAS 2/3 - NON-ADHERENT', $result->tierLabel);
    }

    // --- Cas d'erreur ---

    public function test_crem_7xxx_throws_la_rochelle_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La Rochelle');

        $this->service->resolve('70001', null);
    }

    public function test_crem_invalid_prefix_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalide');

        $this->service->resolve('20001', null);
    }

    public function test_no_crem_no_level_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->resolve(null, null);
    }
}
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
php artisan test tests/Feature/TierResolverServiceTest.php
```

Expected : FAIL (classe TierResolverService introuvable)

- [ ] **Step 3 : Créer TierResult**

```php
<?php
// app/Services/TierResult.php

declare(strict_types=1);

namespace App\Services;

readonly class TierResult
{
    private const LABELS = [
        'las1_adherent'           => 'LAS 1 - ADHERENT',
        'las1_adherent_sans_tuto' => 'LAS 1 - ADHERENT CREM SANS TUTORAT',
        'las1_non_adherent'       => 'LAS 1 - NON-ADHERENT',
        'las2_adherent'           => 'LAS 2/3 - ADHERENT',
        'las2_adherent_sans_tuto' => 'LAS 2/3 - ADHERENT CREM SANS TUTORAT',
        'las2_non_adherent'       => 'LAS 2/3 - NON-ADHERENT',
    ];

    public string $tierLabel;

    public function __construct(public string $tierKey)
    {
        $this->tierLabel = self::LABELS[$tierKey];
    }
}
```

- [ ] **Step 4 : Créer TierResolverService**

```php
<?php
// app/Services/TierResolverService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\TutoringMember;
use InvalidArgumentException;

class TierResolverService
{
    public function resolve(?string $cremNumber, ?string $lasLevel): TierResult
    {
        if ($cremNumber !== null) {
            return $this->resolveFromCrem($cremNumber);
        }

        return $this->resolveFromLevel($lasLevel);
    }

    private function resolveFromCrem(string $cremNumber): TierResult
    {
        $prefix = $cremNumber[0] ?? '';

        return match ($prefix) {
            '7'     => throw new InvalidArgumentException(
                'Ce numéro correspond à un établissement La Rochelle, non pris en charge ici.'
            ),
            '1'     => new TierResult(
                TutoringMember::where('crem_number', $cremNumber)->exists()
                    ? 'las1_adherent'
                    : 'las1_adherent_sans_tuto'
            ),
            '9'     => new TierResult(
                TutoringMember::where('crem_number', $cremNumber)->exists()
                    ? 'las2_adherent'
                    : 'las2_adherent_sans_tuto'
            ),
            default => throw new InvalidArgumentException(
                'Numéro CREM invalide. Les numéros valides commencent par 1 ou 9.'
            ),
        };
    }

    private function resolveFromLevel(?string $lasLevel): TierResult
    {
        return match ($lasLevel) {
            'las1'  => new TierResult('las1_non_adherent'),
            'las2'  => new TierResult('las2_non_adherent'),
            default => throw new InvalidArgumentException(
                'Sélectionnez votre niveau LAS si vous n\'avez pas de numéro CREM.'
            ),
        };
    }
}
```

- [ ] **Step 5 : Vérifier que les tests passent**

```bash
php artisan test tests/Feature/TierResolverServiceTest.php
```

Expected : 9 tests passed

- [ ] **Step 6 : Commit**

```bash
git add app/Services/TierResult.php app/Services/TierResolverService.php tests/Feature/TierResolverServiceTest.php
git commit -m "feat: add TierResolverService with full test coverage"
```

---

## Task 3 — Configuration (services.php + .env.example)

**Files:**
- Modify: `config/services.php`
- Modify: `.env.example`

- [ ] **Step 1 : Ajouter la section inscription_forms dans config/services.php**

Dans `config/services.php`, remplacer le bloc `helloasso` existant par :

```php
    'helloasso' => [
        'client_id'     => env('HELLOASSO_CLIENT_ID'),
        'client_secret' => env('HELLOASSO_CLIENT_SECRET'),
        'org_slug'      => env('HELLOASSO_ORG_SLUG', 'comite-regional-des-etudiants-en-medecine-de-poitiers'),
        'form_slug'     => env('HELLOASSO_FORM_SLUG', 'examen-blanc-s2'),
        'form_type'     => env('HELLOASSO_FORM_TYPE', 'Event'),
        'webhook_secret' => env('HELLOASSO_WEBHOOK_SECRET'),
        'inscription_forms' => [
            'las1_adherent'           => env('HA_FORM_LAS1_ADHERENT'),
            'las1_adherent_sans_tuto' => env('HA_FORM_LAS1_SANS_TUTO'),
            'las1_non_adherent'       => env('HA_FORM_LAS1_NON_ADHERENT'),
            'las2_adherent'           => env('HA_FORM_LAS2_ADHERENT'),
            'las2_adherent_sans_tuto' => env('HA_FORM_LAS2_SANS_TUTO'),
            'las2_non_adherent'       => env('HA_FORM_LAS2_NON_ADHERENT'),
        ],
    ],
```

- [ ] **Step 2 : Ajouter les variables dans .env.example**

Ajouter à la fin de `.env.example` :

```
HELLOASSO_CLIENT_ID=
HELLOASSO_CLIENT_SECRET=
HELLOASSO_ORG_SLUG=comite-regional-des-etudiants-en-medecine-de-poitiers
HELLOASSO_FORM_SLUG=examen-blanc-s2
HELLOASSO_FORM_TYPE=Event
HELLOASSO_WEBHOOK_SECRET=

HA_FORM_LAS1_ADHERENT=
HA_FORM_LAS1_SANS_TUTO=
HA_FORM_LAS1_NON_ADHERENT=
HA_FORM_LAS2_ADHERENT=
HA_FORM_LAS2_SANS_TUTO=
HA_FORM_LAS2_NON_ADHERENT=
```

- [ ] **Step 3 : Vider le cache de config**

```bash
php artisan config:clear
```

Expected : Configuration cache cleared successfully.

- [ ] **Step 4 : Commit**

```bash
git add config/services.php .env.example
git commit -m "feat: add HelloAsso inscription form config and webhook secret"
```

---

## Task 4 — InscriptionController + Vue + Routes

**Files:**
- Create: `app/Http/Controllers/InscriptionController.php`
- Create: `resources/views/public/inscriptions.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InscriptionControllerTest.php`

- [ ] **Step 1 : Écrire les tests (ils doivent échouer)**

```php
<?php
// tests/Feature/InscriptionControllerTest.php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TutoringMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- GET /inscriptions ----

    public function test_get_inscriptions_shows_step_1_by_default(): void
    {
        $response = $this->get('/inscriptions');

        $response->assertStatus(200);
        $response->assertSee('Numéro CREM');
        $response->assertDontSee('iframe');
    }

    public function test_get_inscriptions_with_flash_shows_step_2(): void
    {
        $response = $this->withSession([
            '_flash' => ['new' => ['tier_key', 'tier_label', 'form_slug'], 'old' => []],
            'tier_key'   => 'las1_adherent',
            'tier_label' => 'LAS 1 - ADHERENT',
            'form_slug'  => 'mon-formulaire-test',
        ])->get('/inscriptions');

        $response->assertStatus(200);
        $response->assertSee('LAS 1 - ADHERENT');
        $response->assertSee('iframe');
        $response->assertSee('mon-formulaire-test');
    }

    // ---- POST /inscriptions/check-tier ----

    public function test_post_check_tier_crem_1xxx_in_tutorat(): void
    {
        TutoringMember::create(['crem_number' => '10001']);

        config(['services.helloasso.inscription_forms.las1_adherent' => 'slug-las1-adherent']);

        $response = $this->post('/inscriptions/check-tier', ['crem_number' => '10001']);

        $response->assertRedirect('/inscriptions');
        $response->assertSessionHas('tier_key', 'las1_adherent');
        $response->assertSessionHas('tier_label', 'LAS 1 - ADHERENT');
        $response->assertSessionHas('form_slug', 'slug-las1-adherent');
    }

    public function test_post_check_tier_crem_9xxx_not_in_tutorat(): void
    {
        config(['services.helloasso.inscription_forms.las2_adherent_sans_tuto' => 'slug-las2-sans-tuto']);

        $response = $this->post('/inscriptions/check-tier', ['crem_number' => '90002']);

        $response->assertRedirect('/inscriptions');
        $response->assertSessionHas('tier_key', 'las2_adherent_sans_tuto');
    }

    public function test_post_check_tier_no_crem_las1(): void
    {
        config(['services.helloasso.inscription_forms.las1_non_adherent' => 'slug-las1-na']);

        $response = $this->post('/inscriptions/check-tier', [
            'crem_number' => '',
            'las_level'   => 'las1',
        ]);

        $response->assertRedirect('/inscriptions');
        $response->assertSessionHas('tier_key', 'las1_non_adherent');
    }

    public function test_post_check_tier_crem_7xxx_redirects_with_error(): void
    {
        $response = $this->post('/inscriptions/check-tier', ['crem_number' => '70001']);

        $response->assertRedirect('/inscriptions');
        $response->assertSessionHas('error');
        $response->assertSessionMissing('tier_key');
    }

    public function test_post_check_tier_invalid_crem_redirects_with_error(): void
    {
        $response = $this->post('/inscriptions/check-tier', ['crem_number' => '20001']);

        $response->assertRedirect('/inscriptions');
        $response->assertSessionHas('error');
    }

    public function test_post_check_tier_no_crem_no_level_redirects_with_error(): void
    {
        $response = $this->post('/inscriptions/check-tier', ['crem_number' => '']);

        $response->assertRedirect('/inscriptions');
        $response->assertSessionHas('error');
    }
}
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
php artisan test tests/Feature/InscriptionControllerTest.php
```

Expected : FAIL (routes introuvables)

- [ ] **Step 3 : Créer InscriptionController**

```php
<?php
// app/Http/Controllers/InscriptionController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TierResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class InscriptionController extends Controller
{
    public function __construct(private readonly TierResolverService $tierResolver) {}

    public function index(): View
    {
        return view('public.inscriptions');
    }

    public function checkTier(Request $request): RedirectResponse
    {
        $cremNumber = $request->input('crem_number') ?: null;
        $lasLevel   = $request->input('las_level');

        try {
            $result   = $this->tierResolver->resolve($cremNumber, $lasLevel ?: null);
            $formSlug = config("services.helloasso.inscription_forms.{$result->tierKey}");

            session()->flash('tier_key', $result->tierKey);
            session()->flash('tier_label', $result->tierLabel);
            session()->flash('form_slug', $formSlug);
        } catch (InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }

        return redirect()->route('inscriptions.index');
    }
}
```

- [ ] **Step 4 : Ajouter les routes dans routes/web.php**

Dans `routes/web.php`, avant le bloc `Route::middleware('guest')`, ajouter :

```php
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\TutoringImportController;

Route::get('/inscriptions', [InscriptionController::class, 'index'])->name('inscriptions.index');
Route::post('/inscriptions/check-tier', [InscriptionController::class, 'checkTier'])
    ->name('inscriptions.check-tier')
    ->middleware('throttle:10,1');

Route::post('/webhooks/helloasso', [WebhookController::class, 'handle'])
    ->name('webhooks.helloasso');
```

Et dans le groupe `Route::middleware('auth')`, ajouter :

```php
    Route::get('/admin/tutoring-import', [TutoringImportController::class, 'index'])
        ->name('admin.tutoring-import');
    Route::post('/admin/tutoring-import', [TutoringImportController::class, 'store'])
        ->name('admin.tutoring-import.store');
```

- [ ] **Step 5 : Créer la vue inscriptions.blade.php**

```blade
{{-- resources/views/public/inscriptions.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — Examen Blanc</title>

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
    <link rel="apple-touch-icon" href="/logo-192.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Figtree', 'ui-sans-serif', 'system-ui'] },
                },
            },
        }
    </script>
</head>

<body class="font-sans antialiased bg-gray-100 min-h-screen">

    <nav class="bg-[#CC2929] text-white shadow-md">
        <div class="max-w-2xl mx-auto px-5 py-3 flex items-center gap-3">
            <img src="/logo-tutorat.png" alt="Tutorat Poitiers" class="h-8 w-8 rounded-full bg-white p-0.5 shrink-0">
            <span class="text-base font-bold tracking-tight">CREM · Inscription Examen Blanc S2</span>
        </div>
    </nav>

    <div class="max-w-lg mx-auto mt-12 px-4">

        @if(session('tier_label'))
            {{-- Étape 2 : paiement --}}
            <div class="bg-white rounded-2xl shadow-sm border p-8">
                <h1 class="text-xl font-bold text-gray-900 mb-1">Votre inscription</h1>
                <p class="text-sm text-gray-500 mb-6">Tarif détecté automatiquement selon votre profil.</p>

                <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-6">
                    <p class="text-sm text-blue-800 font-medium">{{ session('tier_label') }}</p>
                </div>

                <p class="text-sm text-gray-600 mb-4">
                    Complétez votre inscription et votre paiement via le formulaire ci-dessous.
                </p>

                <iframe
                    id="helloasso-widget"
                    src="https://www.helloasso.com/associations/{{ config('services.helloasso.org_slug') }}/evenements/{{ session('form_slug') }}/widget"
                    style="width:100%;height:750px;border:none;"
                    allow="payment"
                    loading="lazy"
                ></iframe>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6">
                <a href="{{ route('inscriptions.index') }}" class="hover:text-gray-600 underline">
                    Recommencer avec un autre profil
                </a>
            </p>

        @else
            {{-- Étape 1 : identification --}}
            <div class="bg-white rounded-2xl shadow-sm border p-8">
                <h1 class="text-xl font-bold text-gray-900 mb-1">Inscription à l'examen blanc</h1>
                <p class="text-sm text-gray-500 mb-6">
                    Saisissez votre numéro CREM pour que nous détections votre tarif automatiquement.
                </p>

                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-5">
                        <p class="text-sm text-red-700">{{ session('error') }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('inscriptions.check-tier') }}" class="space-y-5" id="inscription-form">
                    @csrf

                    <div>
                        <label for="crem_number" class="block text-sm font-medium text-gray-700 mb-1">
                            Numéro CREM
                        </label>
                        <input type="text" name="crem_number" id="crem_number"
                               value="{{ old('crem_number') }}"
                               placeholder="Ex : 12345"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#CC2929] focus:border-transparent">
                    </div>

                    <div id="no-crem-section" class="hidden">
                        <p class="text-sm font-medium text-gray-700 mb-2">Votre niveau LAS :</p>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="radio" name="las_level" value="las1"
                                       class="text-[#CC2929] focus:ring-[#CC2929]"
                                       {{ old('las_level') === 'las1' ? 'checked' : '' }}>
                                LAS 1
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="radio" name="las_level" value="las2"
                                       class="text-[#CC2929] focus:ring-[#CC2929]"
                                       {{ old('las_level') === 'las2' ? 'checked' : '' }}>
                                LAS 2 / LAS 3
                            </label>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" id="no-crem-checkbox"
                               class="rounded text-[#CC2929] focus:ring-[#CC2929]"
                               {{ old('las_level') ? 'checked' : '' }}>
                        Je n'ai pas de numéro CREM
                    </label>

                    <button type="submit"
                            class="w-full px-4 py-2.5 bg-[#CC2929] hover:bg-[#A81E1E] text-white font-medium rounded-lg transition">
                        Continuer
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6">
                <a href="{{ route('public.placement') }}" class="hover:text-gray-600 underline">Voir les placements</a>
            </p>
        @endif

    </div>

    <script>
        const checkbox = document.getElementById('no-crem-checkbox');
        const noCremSection = document.getElementById('no-crem-section');
        const cremInput = document.getElementById('crem_number');

        function toggleNoCrem() {
            if (checkbox.checked) {
                noCremSection.classList.remove('hidden');
                cremInput.value = '';
                cremInput.disabled = true;
            } else {
                noCremSection.classList.add('hidden');
                cremInput.disabled = false;
            }
        }

        checkbox.addEventListener('change', toggleNoCrem);
        toggleNoCrem(); // appliquer l'état initial (si old() est rempli)
    </script>

</body>
</html>
```

- [ ] **Step 6 : Vérifier que les tests passent**

```bash
php artisan test tests/Feature/InscriptionControllerTest.php
```

Expected : 7 tests passed

- [ ] **Step 7 : Commit**

```bash
git add app/Http/Controllers/InscriptionController.php \
        resources/views/public/inscriptions.blade.php \
        routes/web.php
git commit -m "feat: add /inscriptions page with 2-step tier detection and HelloAsso iframe"
```

---

## Task 5 — WebhookController + exclusion CSRF

**Files:**
- Create: `app/Http/Controllers/WebhookController.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/WebhookControllerTest.php`

Le webhook est déjà déclaré dans les routes (ajouté à la task 4). Il faut l'exclure du CSRF et implémenter la vérification HMAC.

- [ ] **Step 1 : Écrire les tests**

```php
<?php
// tests/Feature/WebhookControllerTest.php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.helloasso.webhook_secret' => $this->secret]);
        config(['services.helloasso.inscription_forms' => [
            'las1_adherent' => 'slug-las1',
        ]]);
    }

    private function makePayload(array $overrides = []): array
    {
        return array_merge([
            'eventType' => 'Order',
            'data' => [
                'id'       => 999,
                'state'    => 'Processed',
                'formSlug' => 'slug-las1',
                'formType' => 'Event',
                'payer'    => [
                    'email'     => 'jean.dupont@example.com',
                    'firstName' => 'Jean',
                    'lastName'  => 'Dupont',
                ],
                'items' => [
                    [
                        'id'    => 12345,
                        'state' => 'Processed',
                        'user'  => ['firstName' => 'Jean', 'lastName' => 'Dupont'],
                        'customFields' => [
                            ['name' => 'Numéro CREM', 'answer' => '10001'],
                        ],
                    ],
                ],
            ],
        ], $overrides);
    }

    private function sign(string $body): string
    {
        return base64_encode(hash_hmac('sha256', $body, $this->secret, true));
    }

    public function test_valid_webhook_creates_student(): void
    {
        $payload = json_encode($this->makePayload());

        $response = $this->postJson('/webhooks/helloasso', json_decode($payload, true), [
            'X-Helloasso-Signature' => $this->sign($payload),
            'Content-Type'          => 'application/json',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('students', [
            'helloasso_item_id'  => 12345,
            'helloasso_order_id' => 999,
            'email'              => 'jean.dupont@example.com',
            'first_name'         => 'Jean',
            'last_name'          => 'Dupont',
            'crem_number'        => '10001',
            'tier_name'          => 'LAS 1 - ADHERENT',
        ]);
    }

    public function test_invalid_signature_returns_401(): void
    {
        $payload = json_encode($this->makePayload());

        $response = $this->postJson('/webhooks/helloasso', json_decode($payload, true), [
            'X-Helloasso-Signature' => 'invalidsignature',
            'Content-Type'          => 'application/json',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_non_order_event_is_ignored(): void
    {
        $payload = json_encode(['eventType' => 'Payment', 'data' => []]);

        $response = $this->postJson('/webhooks/helloasso', json_decode($payload, true), [
            'X-Helloasso-Signature' => $this->sign($payload),
            'Content-Type'          => 'application/json',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_duplicate_webhook_does_not_create_duplicate_student(): void
    {
        $payload = json_encode($this->makePayload());
        $headers = [
            'X-Helloasso-Signature' => $this->sign($payload),
            'Content-Type'          => 'application/json',
        ];
        $body = json_decode($payload, true);

        $this->postJson('/webhooks/helloasso', $body, $headers);
        $this->postJson('/webhooks/helloasso', $body, $headers);

        $this->assertDatabaseCount('students', 1);
    }

    public function test_order_with_non_processed_state_is_ignored(): void
    {
        $payload = json_encode($this->makePayload(['data' => array_merge(
            $this->makePayload()['data'],
            ['state' => 'Pending']
        )]));

        $response = $this->postJson('/webhooks/helloasso', json_decode($payload, true), [
            'X-Helloasso-Signature' => $this->sign($payload),
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('students', 0);
    }
}
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
php artisan test tests/Feature/WebhookControllerTest.php
```

Expected : FAIL (classe WebhookController introuvable ou 419 CSRF)

- [ ] **Step 3 : Exclure le webhook du CSRF dans bootstrap/app.php**

Remplacer le callback `->withMiddleware()` vide par :

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/helloasso',
        ]);
    })
```

- [ ] **Step 4 : Créer WebhookController**

```php
<?php
// app/Http/Controllers/WebhookController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('X-Helloasso-Signature', '');
        $secret    = config('services.helloasso.webhook_secret');

        if (!$this->isValidSignature($rawBody, $signature, $secret)) {
            return response('Unauthorized', 401);
        }

        $payload   = $request->all();
        $eventType = $payload['eventType'] ?? '';
        $data      = $payload['data'] ?? [];

        if ($eventType !== 'Order' || ($data['state'] ?? '') !== 'Processed') {
            return response('OK', 200);
        }

        $this->processOrder($data);

        return response('OK', 200);
    }

    private function isValidSignature(string $body, string $signature, ?string $secret): bool
    {
        if (!$secret) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return hash_equals($expected, $signature);
    }

    private function processOrder(array $data): void
    {
        $orderId   = $data['id'] ?? null;
        $formSlug  = $data['formSlug'] ?? '';
        $tierName  = $this->resolveTierName($formSlug);
        $email     = $data['payer']['email'] ?? null;

        foreach ($data['items'] ?? [] as $item) {
            if (($item['state'] ?? '') !== 'Processed') {
                continue;
            }

            $itemId    = $item['id'] ?? null;
            $firstName = $item['user']['firstName'] ?? ($data['payer']['firstName'] ?? null);
            $lastName  = $item['user']['lastName'] ?? ($data['payer']['lastName'] ?? null);
            $crem      = $this->extractCremNumber($item['customFields'] ?? []);

            if (!$itemId) {
                continue;
            }

            Student::updateOrCreate(
                ['helloasso_item_id' => $itemId],
                [
                    'helloasso_order_id' => $orderId,
                    'first_name'         => $firstName ?? '',
                    'last_name'          => $lastName ?? '',
                    'email'              => $email ?? '',
                    'tier_name'          => $tierName ?? '',
                    'crem_number'        => $crem,
                    'synced_at'          => now(),
                ]
            );
        }
    }

    private function resolveTierName(string $formSlug): ?string
    {
        $forms = config('services.helloasso.inscription_forms', []);
        $keyBySlug = array_flip($forms);
        $tierKey   = $keyBySlug[$formSlug] ?? null;

        $labels = [
            'las1_adherent'           => 'LAS 1 - ADHERENT',
            'las1_adherent_sans_tuto' => 'LAS 1 - ADHERENT CREM SANS TUTORAT',
            'las1_non_adherent'       => 'LAS 1 - NON-ADHERENT',
            'las2_adherent'           => 'LAS 2/3 - ADHERENT',
            'las2_adherent_sans_tuto' => 'LAS 2/3 - ADHERENT CREM SANS TUTORAT',
            'las2_non_adherent'       => 'LAS 2/3 - NON-ADHERENT',
        ];

        return $tierKey ? ($labels[$tierKey] ?? null) : null;
    }

    private function extractCremNumber(array $customFields): ?string
    {
        foreach ($customFields as $field) {
            $name = strtolower($field['name'] ?? '');
            if (str_contains($name, 'crem')) {
                $value = trim($field['answer'] ?? '');
                return $value !== '' ? $value : null;
            }
        }

        return null;
    }
}
```

- [ ] **Step 5 : Vérifier que les tests passent**

```bash
php artisan test tests/Feature/WebhookControllerTest.php
```

Expected : 5 tests passed

- [ ] **Step 6 : Commit**

```bash
git add app/Http/Controllers/WebhookController.php \
        bootstrap/app.php \
        tests/Feature/WebhookControllerTest.php
git commit -m "feat: add HelloAsso webhook handler with HMAC verification and student upsert"
```

---

## Task 6 — TutoringImportController + Vue admin

**Files:**
- Create: `app/Http/Controllers/TutoringImportController.php`
- Create: `resources/views/admin/tutoring-import.blade.php`
- Test: `tests/Feature/TutoringImportControllerTest.php`

Les routes admin ont été ajoutées à la task 4. Le parsing Excel utilise PhpSpreadsheet directement (déjà disponible via `maatwebsite/excel`).

- [ ] **Step 1 : Écrire les tests**

```php
<?php
// tests/Feature/TutoringImportControllerTest.php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TutoringMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TutoringImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createExcelFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_unauthenticated_user_cannot_access_import_page(): void
    {
        $response = $this->get('/admin/tutoring-import');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_import_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/tutoring-import');

        $response->assertStatus(200);
        $response->assertSee('Import');
    }

    public function test_upload_valid_excel_inserts_members(): void
    {
        $user = User::factory()->create();
        $file = $this->createExcelFile([
            ['Numéro CREM', 'Prénom', 'Nom'],
            ['10001', 'Alice', 'Martin'],
            ['90002', 'Bob', 'Durand'],
        ]);

        $response = $this->actingAs($user)->post('/admin/tutoring-import', ['excel' => $file]);

        $response->assertRedirect('/admin/tutoring-import');
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('tutoring_members', 2);
        $this->assertDatabaseHas('tutoring_members', ['crem_number' => '10001', 'first_name' => 'Alice']);
        $this->assertDatabaseHas('tutoring_members', ['crem_number' => '90002', 'first_name' => 'Bob']);
    }

    public function test_upload_truncates_previous_data(): void
    {
        TutoringMember::create(['crem_number' => '11111']);
        $user = User::factory()->create();
        $file = $this->createExcelFile([
            ['Numéro CREM'],
            ['99999'],
        ]);

        $this->actingAs($user)->post('/admin/tutoring-import', ['excel' => $file]);

        $this->assertDatabaseCount('tutoring_members', 1);
        $this->assertDatabaseHas('tutoring_members', ['crem_number' => '99999']);
        $this->assertDatabaseMissing('tutoring_members', ['crem_number' => '11111']);
    }

    public function test_upload_without_crem_column_returns_error(): void
    {
        $user = User::factory()->create();
        $file = $this->createExcelFile([
            ['Prénom', 'Nom'],
            ['Alice', 'Martin'],
        ]);

        $response = $this->actingAs($user)->post('/admin/tutoring-import', ['excel' => $file]);

        $response->assertRedirect('/admin/tutoring-import');
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('tutoring_members', 0);
    }

    public function test_import_without_file_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/tutoring-import', []);

        $response->assertSessionHasErrors('excel');
    }
}
```

- [ ] **Step 2 : Vérifier que les tests échouent**

```bash
php artisan test tests/Feature/TutoringImportControllerTest.php
```

Expected : FAIL (classe TutoringImportController introuvable)

- [ ] **Step 3 : Créer TutoringImportController**

```php
<?php
// app/Http/Controllers/TutoringImportController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TutoringMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TutoringImportController extends Controller
{
    public function index(): View
    {
        $count     = TutoringMember::count();
        $lastImport = TutoringMember::max('created_at');

        return view('admin.tutoring-import', compact('count', 'lastImport'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'excel' => 'required|file|mimes:xlsx,xls',
        ]);

        $path        = $request->file('excel')->getPathname();
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, false, false, false);

        if (empty($rows)) {
            return redirect()->route('admin.tutoring-import')
                ->with('error', 'Le fichier est vide.');
        }

        $headers = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);

        $cremCol      = $this->findColumnIndex($headers, 'crem');
        $firstNameCol = $this->findColumnIndex($headers, 'prénom') ?? $this->findColumnIndex($headers, 'prenom');
        $lastNameCol  = $this->findColumnIndex($headers, 'nom');

        if ($cremCol === null) {
            return redirect()->route('admin.tutoring-import')
                ->with('error', 'Aucune colonne CREM détectée dans le fichier. Vérifiez les en-têtes.');
        }

        $members = [];
        $now     = now();

        foreach (array_slice($rows, 1) as $row) {
            $crem = trim((string) ($row[$cremCol] ?? ''));
            if ($crem === '') {
                continue;
            }

            $members[] = [
                'crem_number' => $crem,
                'first_name'  => $firstNameCol !== null ? trim((string) ($row[$firstNameCol] ?? '')) ?: null : null,
                'last_name'   => $lastNameCol !== null ? trim((string) ($row[$lastNameCol] ?? '')) ?: null : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        DB::transaction(function () use ($members): void {
            TutoringMember::truncate();
            if (!empty($members)) {
                TutoringMember::insert($members);
            }
        });

        return redirect()->route('admin.tutoring-import')
            ->with('success', count($members) . ' membres importés avec succès.');
    }

    private function findColumnIndex(array $headers, string $needle): ?int
    {
        foreach ($headers as $index => $header) {
            if (str_contains($header, $needle)) {
                return $index;
            }
        }

        return null;
    }
}
```

- [ ] **Step 4 : Créer la vue admin/tutoring-import.blade.php**

```blade
{{-- resources/views/admin/tutoring-import.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import liste tutorat — Admin</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Figtree', 'ui-sans-serif', 'system-ui'] } } }
        }
    </script>
</head>
<body class="font-sans antialiased bg-gray-100 min-h-screen">

    <nav class="bg-[#CC2929] text-white shadow-md">
        <div class="max-w-2xl mx-auto px-5 py-3 flex items-center gap-3">
            <img src="/logo-tutorat.png" alt="Tutorat Poitiers" class="h-8 w-8 rounded-full bg-white p-0.5 shrink-0">
            <span class="text-base font-bold tracking-tight">Admin · Import liste tutorat S2</span>
        </div>
    </nav>

    <div class="max-w-lg mx-auto mt-12 px-4">
        <div class="bg-white rounded-2xl shadow-sm border p-8">
            <h1 class="text-xl font-bold text-gray-900 mb-1">Import de la liste tutorat S2</h1>

            {{-- État actuel --}}
            <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 mb-6 text-sm text-gray-600">
                @if($count > 0)
                    <p><strong>{{ $count }}</strong> membres dans la liste actuelle.</p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Dernier import : {{ \Carbon\Carbon::parse($lastImport)->format('d/m/Y à H:i') }}
                    </p>
                @else
                    <p>Aucune liste chargée pour le moment.</p>
                @endif
            </div>

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 mb-5">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-5">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-5">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.tutoring-import.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <div>
                    <label for="excel" class="block text-sm font-medium text-gray-700 mb-1">
                        Fichier Excel (.xlsx ou .xls)
                    </label>
                    <p class="text-xs text-gray-500 mb-2">
                        La première ligne doit contenir les en-têtes. La colonne contenant "CREM" est obligatoire. Les colonnes "Prénom" et "Nom" sont optionnelles.
                    </p>
                    <input type="file" name="excel" id="excel" accept=".xlsx,.xls" required
                           class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#CC2929] file:text-white hover:file:bg-[#A81E1E] file:cursor-pointer">
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-700">
                    <strong>Attention :</strong> L'import remplace intégralement la liste précédente.
                </div>

                <button type="submit"
                        class="w-full px-4 py-2.5 bg-[#CC2929] hover:bg-[#A81E1E] text-white font-medium rounded-lg transition">
                    Importer la liste
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">
            <a href="{{ route('dashboard') }}" class="hover:text-gray-600 underline">← Retour au tableau de bord</a>
        </p>
    </div>

</body>
</html>
```

- [ ] **Step 5 : Vérifier que les tests passent**

```bash
php artisan test tests/Feature/TutoringImportControllerTest.php
```

Expected : 5 tests passed

- [ ] **Step 6 : Lancer toute la suite de tests**

```bash
php artisan test
```

Expected : tous les tests passent (les tests Breeze/Auth existants inclus)

- [ ] **Step 7 : Commit final**

```bash
git add app/Http/Controllers/TutoringImportController.php \
        resources/views/admin/tutoring-import.blade.php \
        tests/Feature/TutoringImportControllerTest.php
git commit -m "feat: add admin tutoring list import (Excel, truncate+insert)"
```

---

## Checklist finale avant mise en production

- [ ] Configurer les 7 variables d'environnement sur le serveur :
  - `HELLOASSO_WEBHOOK_SECRET`
  - `HA_FORM_LAS1_ADHERENT`, `HA_FORM_LAS1_SANS_TUTO`, `HA_FORM_LAS1_NON_ADHERENT`
  - `HA_FORM_LAS2_ADHERENT`, `HA_FORM_LAS2_SANS_TUTO`, `HA_FORM_LAS2_NON_ADHERENT`
- [ ] Enregistrer l'URL webhook `https://[votre-domaine]/webhooks/helloasso` dans HelloAsso (backoffice association)
- [ ] Faire un premier import de la liste tutorat S2 depuis `/admin/tutoring-import`
- [ ] Tester le flux complet : saisir un CREM → vérifier le widget affiché → paiement test → vérifier le student créé en base
