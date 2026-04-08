<?php

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
