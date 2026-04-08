<?php

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
