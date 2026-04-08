<?php

namespace App\Console\Commands;

use App\Services\SeatLayoutService;
use Illuminate\Console\Command;

class ImportSeats extends Command
{
    protected $signature   = 'import:seats';
    protected $description = 'Importe les plans de salle depuis le fichier Excel';

    public function handle(SeatLayoutService $service): int
    {
        $this->info('Import des plans de salle...');

        $results = $service->importAll();

        foreach ($results as $amphi => $result) {
            if ($result['status'] === 'ok') {
                $this->line("  ✓ {$amphi} : {$result['count']} places");
            } else {
                $this->warn("  ✗ {$amphi} : feuille introuvable");
            }
        }

        $this->info('Import terminé.');
        return Command::SUCCESS;
    }
}
