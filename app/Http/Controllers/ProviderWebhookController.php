<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Events\SendOrderGotPush;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\OrderAddress;
use Illuminate\Support\Facades\Auth;
use App\Enums\Source;

class ProviderWebhookController extends Controller
{
    // -------------------------------------------------------------------------
    // Public webhook endpoints
    // -------------------------------------------------------------------------

    public function uberEats(Request $request)
    {
        return $this->handleIncoming('uber_eats', $request);
    }

    public function deliveroo(Request $request)
    {
        return $this->handleIncoming('deliveroo', $request);
    }

    public function justEat(Request $request)
    {
        return $this->handleIncoming('just_eat', $request);
    }

    // -------------------------------------------------------------------------
    // Core handler
    // -------------------------------------------------------------------------

    private function handleIncoming(string $providerSource, Request $request)
    {
        try {
            $payload = $request->all();
            Log::info("[{$providerSource}] webhook received", $payload);

            // Normalise provider order ID across different payload shapes
            $providerOrderId = $payload['order_id']
                ?? $payload['id']
                ?? $payload['orderId']
                ?? null;

            $incomingStatus = $payload['status'] ?? null;

            // ------------------------------------------------------------------
            // Check if this is a STATUS UPDATE for an existing order
            // ------------------------------------------------------------------
            $existingOrder = Order::withoutGlobalScopes()
                ->where('provider_order_id', $providerOrderId)
                ->first();

            if ($existingOrder && $incomingStatus) {
                $existingOrder->update([
                    'provider_status'  => $incomingStatus,
                    'provider_payload' => $payload,
                ]);

                Log::info("[{$providerSource}] status update for order #{$existingOrder->order_serial_no} -> {$incomingStatus}");

                return response()->json([
                    'received' => true,
                    'type'     => 'status_update',
                    'order_id' => $existingOrder->id,
                ]);
            }

            // ------------------------------------------------------------------
            // NEW order from provider — create it and fire FCM popup
            // ------------------------------------------------------------------
            $order = $this->createProviderOrder($providerSource, $providerOrderId, $payload);

            // Fire the exact same event the rest of the app uses
            // → SendOrderGotPushNotification listener → OrderGotPushNotificationBuilder → FirebaseService
            event(new SendOrderGotPush(['order_id' => $order->id]));

            return response()->json([
                'received'        => true,
                'type'            => 'new_order',
                'order_id'        => $order->id,
                'order_serial_no' => $order->order_serial_no,
            ]);

        } catch (\Exception $e) {
            Log::error("[{$providerSource}] webhook error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // -------------------------------------------------------------------------
    // Create order from provider payload
    // -------------------------------------------------------------------------

    private function createProviderOrder(
    string  $providerSource,
    ?string $providerOrderId,
    array   $payload
    ): Order {
        // Auto-resolve branch and user if not provided
        $branchId = $payload['branch_id'] ?? \App\Models\Branch::first()->id;
        $userId   = $payload['user_id']   ?? \App\Models\User::role(\App\Enums\Role::ADMIN)
                        ->where(function($q) use ($branchId) {
                            $q->where('branch_id', $branchId)->orWhere('branch_id', 0);
                        })->first()->id;

        $order = Order::create([
            'order_serial_no'   => $payload['order_serial_no'] ?? strtoupper(uniqid('ORD-')),
            'token'             => Str::uuid(),
            'user_id'           => $userId,
            'branch_id'         => $branchId,
            'subtotal'          => $payload['subtotal'] ?? ($payload['total'] ?? 0),
            'discount'          => $payload['discount'] ?? 0,
            'delivery_charge'   => $payload['delivery_charge'] ?? 0,
            'total_tax'         => $payload['total_tax'] ?? 0,
            'total'             => $payload['total'] ?? 0,
            'order_type'        => $payload['order_type'] ?? \App\Enums\OrderType::DELIVERY,
            'order_datetime'    => now(),
            'delivery_time'     => $payload['delivery_time'] ?? null,
            'preparation_time'  => $payload['preparation_time'] ?? 20,
            'is_advance_order'  => $payload['is_advance_order'] ?? 0,
            'payment_method'    => $payload['payment_method'] ?? 1,
            'payment_status'    => $payload['payment_status'] ?? 2,
            'status'            => OrderStatus::PENDING,
            'provider_source'   => $providerSource,
            'provider_order_id' => $providerOrderId ?? ('MOCK-' . strtoupper(uniqid())),
            'provider_status'   => $payload['status'] ?? 'received',
            'provider_payload'  => $payload,
            'source'            => Source::WEB,
        ]);

        OrderAddress::create([
            'order_id'  => $order->id,
            'user_id'   => $order->user_id,
            'label'     => $payload['label']     ?? 'Delivery Address',
            'address'   => $payload['address']   ?? null,
            'apartment' => $payload['apartment'] ?? null,
            'latitude'  => $payload['latitude']  ?? null,
            'longitude' => $payload['longitude'] ?? null,
        ]);

        return $order;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function providerLabel(string $source): string
    {
        return match ($source) {
            'uber_eats' => 'Uber Eats',
            'deliveroo' => 'Deliveroo',
            'just_eat'  => 'Just Eat',
            default     => ucfirst(str_replace('_', ' ', $source)),
        };
    }
}