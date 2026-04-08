<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncHelloAsso extends Command
{
    protected $signature = 'sync:helloasso';

    protected $description = 'Synchronise les inscrits depuis HelloAsso';

    public function handle(SyncService $sync): int
    {
        $this->info('Synchronisation HelloAsso en cours...');

        try {
            $log = $sync->sync();
            $this->info("Terminé : {$log->new_records} nouveaux, {$log->updated_records} mis à jour.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erreur : '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
