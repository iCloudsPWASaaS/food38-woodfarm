<?php

namespace App\Services\Providers;

use App\Models\MerchantConfig;
use Illuminate\Support\Facades\Http;

class DeliverooService implements DeliveryProviderInterface
{
    private ?MerchantConfig $config;
    private MockDeliveryService $mock;

    public function __construct()
    {
        $this->config = MerchantConfig::where('provider', 'deliveroo')->first();
        $this->mock   = new MockDeliveryService('deliveroo');
    }

    public function isLive(): bool
    {
        return $this->config
            && $this->config->is_live
            && !empty($this->config->client_id)
            && !empty($this->config->client_secret);
    }

    private function getAccessToken(): string
    {
        $response = Http::asForm()->post('https://api.deliveroo.com/oauth2/token', [
            'client_id'     => $this->config->client_id,
            'client_secret' => $this->config->client_secret,
            'grant_type'    => 'client_credentials',
        ]);

        return $response->json('access_token');
    }

    public function createOrder(array $orderData): array
    {
        if (!$this->isLive()) {
            return $this->mock->createOrder($orderData);
        }

        $token    = $this->getAccessToken();
        $response = Http::withToken($token)
            ->post("https://api.deliveroo.com/order/v1/orders", [
                'location_id' => $this->config->store_id,
                'external_id' => $orderData['order_serial_no'],
                'items'       => $orderData['items'] ?? [],
            ]);

        return [
            'success'           => $response->successful(),
            'provider_order_id' => $response->json('id'),
            'provider_status'   => 'accepted',
            'raw'               => $response->json(),
        ];
    }

    public function cancelOrder(string $orderId): bool
    {
        if (!$this->isLive()) {
            return $this->mock->cancelOrder($orderId);
        }

        $token    = $this->getAccessToken();
        $response = Http::withToken($token)
            ->post("https://api.deliveroo.com/order/v1/orders/{$orderId}/cancel");

        return $response->successful();
    }

    public function getOrderStatus(string $orderId): string
    {
        if (!$this->isLive()) {
            return $this->mock->getOrderStatus($orderId);
        }

        $token    = $this->getAccessToken();
        $response = Http::withToken($token)
            ->get("https://api.deliveroo.com/order/v1/orders/{$orderId}");

        return $response->json('status', 'unknown');
    }
}
