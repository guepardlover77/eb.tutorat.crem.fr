<?php

// app/Http/Controllers/WebhookController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\TierResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Helloasso-Signature', '');
        $secret = config('services.helloasso.webhook_secret');

        if (! $this->isValidSignature($rawBody, $signature, $secret)) {
            return response('Unauthorized', 401);
        }

        $payload = json_decode($rawBody, true) ?? [];
        $eventType = $payload['eventType'] ?? '';
        $data = $payload['data'] ?? [];

        if ($eventType !== 'Order' || ($data['state'] ?? '') !== 'Processed') {
            return response('OK', 200);
        }

        $this->processOrder($data);

        return response('OK', 200);
    }

    private function isValidSignature(string $body, string $signature, ?string $secret): bool
    {
        if (! $secret) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return hash_equals($expected, $signature);
    }

    private function processOrder(array $data): void
    {
        $orderId = $data['id'] ?? null;
        $formSlug = $data['formSlug'] ?? '';
        $tierName = $this->resolveTierName($formSlug);
        $email = $data['payer']['email'] ?? null;

        foreach ($data['items'] ?? [] as $item) {
            if (($item['state'] ?? '') !== 'Processed') {
                continue;
            }

            $itemId = $item['id'] ?? null;
            $firstName = $item['user']['firstName'] ?? ($data['payer']['firstName'] ?? null);
            $lastName = $item['user']['lastName'] ?? ($data['payer']['lastName'] ?? null);
            $crem = $this->extractCremNumber($item['customFields'] ?? []);

            if (! $itemId) {
                continue;
            }

            Student::updateOrCreate(
                ['helloasso_item_id' => $itemId],
                [
                    'helloasso_order_id' => $orderId,
                    'first_name' => $firstName ?? '',
                    'last_name' => $lastName ?? '',
                    'email' => $email ?? '',
                    'tier_name' => $tierName ?? '',
                    'crem_number' => $crem,
                    'synced_at' => now(),
                ]
            );
        }
    }

    private function resolveTierName(string $formSlug): ?string
    {
        $forms = config('services.helloasso.inscription_forms', []);
        $keyBySlug = array_flip($forms);
        $tierKey = $keyBySlug[$formSlug] ?? null;

        return $tierKey ? (TierResult::LABELS[$tierKey] ?? null) : null;
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
