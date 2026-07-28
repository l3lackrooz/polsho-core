<?php

namespace App\Domain\Market\Infrastructure\Support\Utility;

class CurrencyNormalizer
{
    public function normalize(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return match ($currency) {
            'IRT', 'TOMAN', 'TMN' => 'IRT',
            'IRR', 'RIAL', 'RLS' => 'IRR',
            default => $currency,
        };
    }

    public function convertPriceTo(string $source, string $target, float $price): float
    {
        $source = $this->normalize($source);
        $target = $this->normalize($target);

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
