<?php

namespace App\Http\Controllers;

use App\Services\HelloAssoService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    public function __construct(private readonly SyncService $syncService) {}

    public function run(): JsonResponse
    {
        if (!HelloAssoService::isConfigured()) {
            return response()->json(['error' => 'HelloAsso n\'est pas configuré. Vérifiez les clés HELLOASSO_CLIENT_ID et HELLOASSO_CLIENT_SECRET dans le fichier .env.'], 503);
        }

        try {
            return response()->json($this->syncService->startSync());
        } catch (\Throwable $e) {
            Log::error('Sync start failed', ['exception' => $e]);
            return response()->json(['error' => 'Erreur lors de la synchronisation.'], 500);
        }
    }

    public function chunk(Request $request): JsonResponse
    {
        if (!HelloAssoService::isConfigured()) {
            return response()->json(['error' => 'HelloAsso n\'est pas configuré.'], 503);
        }

        $validated = $request->validate(['log_id' => 'required|integer|exists:sync_logs,id']);

        try {
            return response()->json($this->syncService->continueSync($validated['log_id']));
        } catch (\Throwable $e) {
            Log::error('Sync chunk failed', ['exception' => $e]);
            return response()->json(['error' => 'Erreur lors de la synchronisation.'], 500);
        }
    }

    public function verify(Request $request): JsonResponse
    {
        if (!HelloAssoService::isConfigured()) {
            return response()->json(['error' => 'HelloAsso n\'est pas configuré.'], 503);
        }

        try {
            $cursor = $request->input('cursor');

            if ($cursor !== null && (!is_string($cursor) || strlen($cursor) > 500)) {
                return response()->json(['error' => 'Curseur invalide.'], 422);
            }

            $result = $this->syncService->verifyPage($cursor);

            $check = $this->syncService->checkMissing($result['items']);

            return response()->json([
                'done'        => $result['next_cursor'] === null,
                'next_cursor' => $result['next_cursor'],
                'missing'     => $check['missing'],
                'deleted'     => $check['deleted'],
                'checked'     => count($result['items']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Sync verify failed', ['exception' => $e]);
            return response()->json(['error' => 'Erreur lors de la vérification.'], 500);
        }
    }
}
