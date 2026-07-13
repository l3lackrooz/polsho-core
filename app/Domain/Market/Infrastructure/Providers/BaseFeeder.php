<?php

namespace App\Domain\Market\Infrastructure\Providers;


abstract class BaseFeeder
{

    protected string $market;

    public function market(): string
    {
        return $this->market;
    }
}
