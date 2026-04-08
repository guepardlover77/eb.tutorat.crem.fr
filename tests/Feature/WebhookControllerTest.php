<?php

// tests/Feature/WebhookControllerTest.php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
                'id' => 999,
                'state' => 'Processed',
                'formSlug' => 'slug-las1',
                'formType' => 'Event',
                'payer' => [
                    'email' => 'jean.dupont@example.com',
                    'firstName' => 'Jean',
                    'lastName' => 'Dupont',
                ],
                'items' => [
                    [
                        'id' => 12345,
                        'state' => 'Processed',
                        'user' => ['firstName' => 'Jean', 'lastName' => 'Dupont'],
                        'customFields' => [
                            ['name' => 'Numéro CREM', 'answer' => '10001'],
                        ],
                    ],
                ],
            ],
        ], $overrides);
    }

    private function sendWebhook(array $payload, ?string $secret = null): TestResponse
    {
        $body = json_encode($payload);
        $secret ??= 'test-webhook-secret';
        $signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return $this->call(
            'POST',
            route('webhooks.helloasso'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-HELLOASSO-SIGNATURE' => $signature],
            $body
        );
    }

    public function test_valid_webhook_creates_student(): void
    {
        $response = $this->sendWebhook($this->makePayload());

        $response->assertStatus(200);
        $this->assertDatabaseHas('students', [
            'helloasso_item_id' => 12345,
            'helloasso_order_id' => 999,
            'email' => 'jean.dupont@example.com',
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'crem_number' => '10001',
            'tier_name' => 'LAS 1 - ADHERENT',
        ]);
    }

    public function test_invalid_signature_returns_401(): void
    {
        $body = json_encode($this->makePayload());

        $response = $this->call(
            'POST',
            route('webhooks.helloasso'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-HELLOASSO-SIGNATURE' => 'invalidsignature'],
            $body
        );

        $response->assertStatus(401);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_non_order_event_is_ignored(): void
    {
        $response = $this->sendWebhook(['eventType' => 'Payment', 'data' => []]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_duplicate_webhook_does_not_create_duplicate_student(): void
    {
        $this->sendWebhook($this->makePayload());
        $this->sendWebhook($this->makePayload());

        $this->assertDatabaseCount('students', 1);
    }

    public function test_order_with_non_processed_state_is_ignored(): void
    {
        $payload = $this->makePayload(['data' => array_merge(
            $this->makePayload()['data'],
            ['state' => 'Pending']
        )]);

        $response = $this->sendWebhook($payload);

        $response->assertStatus(200);
        $this->assertDatabaseCount('students', 0);
    }
}
