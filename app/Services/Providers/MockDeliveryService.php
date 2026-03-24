<?php

namespace App\Services\Providers;

class MockDeliveryService implements DeliveryProviderInterface
{
    private string $provider;

    public function __construct(string $provider)
    {
        $this->provider = $provider;
    }

    public function isLive(): bool
    {
        return false;
    }

    public function createOrder(array $orderData): array
    {
        return [
            'success'            => true,
            'provider_order_id'  => 'MOCK-' . strtoupper(str_replace('_', '', $this->provider)) . '-' . strtoupper(uniqid()),
            'provider_status'    => 'accepted',
            'estimated_pickup'   => now()->addMinutes(15)->toISOString(),
            'estimated_delivery' => now()->addMinutes(45)->toISOString(),
            'mock'               => true,
        ];
    }

    public function cancelOrder(string $orderId): bool
    {
        return true;
    }

    public function getOrderStatus(string $orderId): string
    {
        return 'accepted';
    }
}
