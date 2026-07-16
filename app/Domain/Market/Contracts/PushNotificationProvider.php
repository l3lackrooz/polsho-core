<?php

namespace App\Domain\Market\Contracts;

use App\Domain\Market\Application\DTO\PushNotificationDeliveryResult;
use App\Domain\Market\Application\DTO\PushNotificationMessage;
use App\Domain\Market\Application\DTO\PushNotificationTarget;

interface PushNotificationProvider
{
    public function key(): string;

    public function send(
        PushNotificationTarget $target,
        PushNotificationMessage $message,
    ): PushNotificationDeliveryResult;
}
