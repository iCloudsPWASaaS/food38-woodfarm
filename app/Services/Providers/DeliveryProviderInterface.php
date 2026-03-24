<?php

namespace App\Services\Providers;

interface DeliveryProviderInterface
{
    public function createOrder(array $orderData): array;
    public function cancelOrder(string $orderId): bool;
    public function getOrderStatus(string $orderId): string;
    public function isLive(): bool;
}
