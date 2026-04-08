<?php

namespace App\Services;

use App\Models\HelloassoToken;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HelloAssoService
{
    private const BASE_URL = 'https://api.helloasso.com/v5';

    private const TOKEN_URL = 'https://api.helloasso.com/oauth2/token';

    private const PAGE_SIZE = 100;

    public static function isConfigured(): bool
    {
        return filled(config('services.helloasso.client_id'))
            && filled(config('services.helloasso.client_secret'));
    }

    public function getValidToken(): string
    {
        $token = HelloassoToken::latest()->first();

        if ($token && $token->isValid()) {
            return $token->access_token;
        }

        return $this->fetchNewToken($token);
    }

    private function fetchNewToken(?HelloassoToken $existing): string
    {
        if ($existing?->refresh_token) {
            try {
                return $this->refreshWithToken($existing);
            } catch (\Exception) {
                // fall through to client_credentials
            }
        }

        return $this->fetchClientCredentialsToken();
    }

    private function refreshWithToken(HelloassoToken $existing): string
    {
        $response = Http::timeout(10)->asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.helloasso.client_id'),
            'client_secret' => config('services.helloasso.client_secret'),
            'refresh_token' => $existing->refresh_token,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Refresh token failed');
        }

        return $this->persistToken($response->json(), $existing);
    }

    private function fetchClientCredentialsToken(): string
    {
        $response = Http::timeout(10)->asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.helloasso.client_id'),
            'client_secret' => config('services.helloasso.client_secret'),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('HelloAsso authentication failed: '.$response->body());
        }

        return $this->persistToken($response->json());
    }

    private function persistToken(array $data, ?HelloassoToken $existing = null): string
    {
        $attributes = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($data['expires_in'] - 60),
        ];

        if ($existing) {
            $existing->update($attributes);
        } else {
            HelloassoToken::create($attributes);
        }

        return $data['access_token'];
    }

    public function fetchPage(?string $cursor): array
    {
        $token = $this->getValidToken();
        $orgSlug = config('services.helloasso.org_slug');
        $formSlug = config('services.helloasso.form_slug');
        $formType = config('services.helloasso.form_type');
        $url = self::BASE_URL."/organizations/{$orgSlug}/forms/{$formType}/{$formSlug}/items";

        $params = ['pageSize' => self::PAGE_SIZE, 'withDetails' => 'true'];
        if ($cursor) {
            $params['continuationToken'] = $cursor;
        }

        $response = Http::withToken($token)->timeout(15)->retry(2, 1000)->get($url, $params);

        if ($response->failed()) {
            $token = $this->fetchClientCredentialsToken();
            $response = Http::withToken($token)->timeout(15)->retry(2, 1000)->get($url, $params);

            if ($response->failed()) {
                throw new RuntimeException('HelloAsso API error: '.$response->body());
            }
        }

        $data = $response->json();
        $items = $data['data'] ?? [];

        $nextCursor = (count($items) === self::PAGE_SIZE)
            ? ($data['pagination']['continuationToken'] ?? null)
            : null;

        return ['items' => $items, 'next_cursor' => $nextCursor];
    }
}
