<?php

namespace App\Services\Providers;

use App\Models\MerchantConfig;
use Illuminate\Support\Facades\Http;

class JustEatService implements DeliveryProviderInterface
{
    private ?MerchantConfig $config;
    private MockDeliveryService $mock;

    public function __construct()
    {
        $this->config = MerchantConfig::where('provider', 'just_eat')->first();
        $this->mock   = new MockDeliveryService('just_eat');
    }

    public function isLive(): bool
    {
        return $this->config
            && $this->config->is_live
            && !empty($this->config->api_key);
    }

    public function createOrder(array $orderData): array
    {
        if (!$this->isLive()) {
            return $this->mock->createOrder($orderData);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config->api_key,
            'Accept'        => 'application/json',
        ])->post("https://api.just-eat.io/restaurants/{$this->config->store_id}/orders", [
            'reference' => $orderData['order_serial_no'],
            'items'     => $orderData['items'] ?? [],
        ]);

        return [
            'success'           => $response->successful(),
            'provider_order_id' => $response->json('orderId'),
            'provider_status'   => 'accepted',
            'raw'               => $response->json(),
        ];
    }

    public function cancelOrder(string $orderId): bool
    {
        if (!$this->isLive()) {
            return $this->mock->cancelOrder($orderId);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config->api_key,
        ])->put("https://api.just-eat.io/orders/{$orderId}/status", [
            'status' => 'Cancelled',
        ]);

        return $response->successful();
    }

    public function getOrderStatus(string $orderId): string
    {
        if (!$this->isLive()) {
            return $this->mock->getOrderStatus($orderId);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config->api_key,
        ])->get("https://api.just-eat.io/orders/{$orderId}");

        return $response->json('status', 'unknown');
    }
}
