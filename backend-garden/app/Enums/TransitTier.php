<?php

declare(strict_types=1);

namespace App\Enums;

enum TransitTier: string
{
    case Express = 'express';
    case Standard = 'standard';
    case Slow = 'slow';

    /**
     * @return array{min:int, max:int, monthly_quota:int|null}
     */
    public function window(): array
    {
        /** @var array{min:int, max:int, monthly_quota:int|null} $config */
        $config = config("postal.tiers.{$this->value}");

        return $config;
    }

    public function monthlyQuota(): ?int
    {
        return $this->window()['monthly_quota'];
    }
}
