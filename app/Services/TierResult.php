<?php

declare(strict_types=1);

namespace App\Services;

readonly class TierResult
{
    public const LABELS = [
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
        if (!array_key_exists($tierKey, self::LABELS)) {
            throw new \InvalidArgumentException("Tier key inconnu : {$tierKey}");
        }

        $this->tierLabel = self::LABELS[$tierKey];
    }
}
