<?php

namespace App\Domain\Market\Application\DTO;

/**
 * A usable quote selected for a single alert evaluation.
 */
class AlertEvaluationPriceDTO
{
    public function __construct(
        public readonly float $price,
        public readonly string $provider,
        public readonly ?int $providerMarketId,
        public readonly bool $isReference,
        public readonly int $timestamp,
    ) {}
}
