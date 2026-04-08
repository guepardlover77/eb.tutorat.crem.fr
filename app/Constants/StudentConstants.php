<?php

namespace App\Constants;

final class StudentConstants
{
    public const EXCLUDED_TIER = "Récupération sans passer l'épreuve";

    public const RECOVERY_OPTIONS = [
        'LAS 1 - NON-ADHERENT',
        'LAS 2/3 - NON-ADHERENT',
        'LAS 1 - ADHERENT CREM SANS TUTORAT',
        'LAS 2/3 - ADHERENT CREM SANS TUTORAT',
        'LAS 1 - ADHERENT',
        'LAS 2/3 - ADHERENT',
    ];
}
