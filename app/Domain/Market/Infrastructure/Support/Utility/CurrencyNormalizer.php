<?php

namespace App\Domain\Market\Infrastructure\Support\Utility;


class CurrencyNormalizer
{
    public function normalize(string $quote): string
    {
        return match (strtoupper($quote)) {
            'IRT', 'TOMAN' => 'IRT',
            'IRR', 'RIAL', 'RLS' => 'IRR',
            default => $quote,
        };
    }

    public function convertPriceTo(string $source, string $target, float $price): float
    {
        $source = strtoupper($source);
        $target = strtoupper($target);

        if ($source === $target) {
            return $price;
        }

        // IRT → IRR
        if ($source === 'IRT' && $target === 'IRR') {
            return $price * 10;
        }

        // IRR → IRT
        if ($source === 'IRR' && $target === 'IRT') {
            return $price / 10;
        }

        return $price;
    }
}
