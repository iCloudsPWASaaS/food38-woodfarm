<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\Source;

use App\Libraries\AppLibrary;
use App\Models\Tax;
use App\Enums\TaxType;

class UberWebhookController extends Controller
{
    // -------------------------------------------------------------------------
    // ENTRY POINT — Uber Webhook (Just receives and stores orders)
    // -------------------------------------------------------------------------

    public function uber(Request $request)
    {
        // Get environment configuration
        $environment = config('services.uber.environment', 'sandbox');
        $isSandbox = ($environment === 'sandbox');
        
        // Signature verification (only enforced in production)
        $signature = $request->header('X-Uber-Signature');
        $secret    = config('services.uber.webhook_secret');
        $computed  = hash_hmac('sha256', $request->getContent(), $secret);
        $isValidSignature = hash_equals($computed, $signature ?? '');
        
        if (!$isValidSignature) {
            Log::warning('[uber] Invalid signature', [
                'received' => $signature,
                'computed' => $computed,
                'environment' => $environment,
            ]);
            
            // Only block request in production mode
            if (!$isSandbox) {
                abort(401, 'Invalid signature');
            }
        }
        
        return $this->handleIncoming('uber', $request);
    }

    // -------------------------------------------------------------------------
    // HANDLE INCOMING WEBHOOK - JUST STORE THE ORDER
    // -------------------------------------------------------------------------

    private function handleIncoming(string $providerSource, Request $request)
    {
        try {
            $payload = $request->all();
            Log::info("[{$providerSource}] webhook received", $payload);

            // Extract Uber order data
            $orderData = $payload['data']['order'] ?? $payload['order'] ?? $payload;
            
            // Extract Uber order ID
            $providerOrderId = $orderData['id'] 
                ?? $payload['order_id'] 
                ?? $payload['id'] 
                ?? $payload['orderId']
                ?? null;

            $eventType = $payload['event_type'] ?? null;
            $incomingStatus = $orderData['status'] ?? $payload['status'] ?? null;

            // ── STATUS UPDATE (order already exists) ──────────────────────────
            if ($providerOrderId) {
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
            }

            // ── NEW ORDER ── JUST STORE IT, DON'T ACCEPT/DECLINE ───────────────
            if ($providerOrderId && !isset($existingOrder)) {
                Log::info("[{$providerSource}] Creating new order", [
                    'provider_order_id' => $providerOrderId
                ]);

                $order = $this->createProviderOrder($providerSource, $providerOrderId, $payload);
                
                return response()->json([
                    'received'        => true,
                    'type'            => 'new_order',
                    'order_id'        => $order->id,
                    'order_serial_no' => $order->order_serial_no,
                    'status'          => 'stored_pending_approval',
                    'message'         => 'Order stored. Use API endpoint to accept or decline.'
                ]);
            }

            // Unknown event
            Log::warning("[{$providerSource}] No action taken", [
                'event_type' => $eventType,
                'provider_order_id' => $providerOrderId
            ]);

            return response()->json([
                'received'   => true,
                'type'       => 'unknown',
                'event_type' => $eventType
            ]);

        } catch (\Exception $e) {
            Log::error("[{$providerSource}] webhook error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 200);
        }
    }

    // -------------------------------------------------------------------------
    // CREATE ORDER IN YOUR DATABASE
    // -------------------------------------------------------------------------

    private function createProviderOrder(string $source, ?string $providerOrderId, array $payload): Order
    {
        $uberOrder = $payload['data']['order'] ?? $payload['order'] ?? $payload;
        $uberCart = $uberOrder['cart'] ?? [];
        $uberPayment = $uberOrder['payment'] ?? [];
        $uberCustomer = $uberOrder['eater'] ?? [];
        $uberAddress = $uberOrder['delivery_address'] ?? $uberOrder['eater']['delivery'] ?? [];

        $branchId = config('services.uber.default_branch_id', 1);
        $userId = config('services.uber.default_user_id', 1);

        // Calculate financials (Uber sends amounts in cents)
        $subtotal = isset($uberPayment['subtotal']) ? $uberPayment['subtotal'] / 100 : 0;
        $total = isset($uberPayment['total_charge']) ? $uberPayment['total_charge'] / 100 : 0;
        $deliveryCharge = isset($uberPayment['delivery_fee']) ? $uberPayment['delivery_fee'] / 100 : 0;
        $tax = isset($uberPayment['tax']) ? $uberPayment['tax'] / 100 : 0;
        $discount = isset($uberPayment['discount']) ? $uberPayment['discount'] / 100 : 0;

        // Create the order
        $order = Order::create([
            'order_serial_no'   => $uberOrder['display_id'] ?? strtoupper(uniqid('ORD-')),
            'token'             => Str::uuid(),
            'user_id'           => $userId,
            'branch_id'         => $branchId,
            'subtotal'          => $subtotal,
            'discount'          => $discount,
            'delivery_charge'   => $deliveryCharge,
            'total_tax'         => $tax,
            'total'             => $total,
            'order_type'        => OrderType::DELIVERY,
            'order_datetime'    => now(),
            'delivery_time'     => $uberOrder['estimated_ready_for_pickup_at'] ?? null,
            'preparation_time'  => $uberOrder['preparation_time'] ?? 20,
            'is_advance_order'  => isset($uberOrder['scheduled_at']) ? 1 : 0,
            'payment_method'    => 1,
            'payment_status'    => 2,
            'status'            => OrderStatus::PENDING,
            'provider_source'   => $source,
            'provider_order_id' => $providerOrderId ?? ('MOCK-' . strtoupper(uniqid())),
            'provider_status'   => $uberOrder['current_state'] ?? 'received',
            'provider_payload'  => $payload,
            'source'            => Source::WEB,
        ]);

        // Create delivery address
        if (!empty($uberAddress)) {
            OrderAddress::create([
                'order_id'  => $order->id,
                'user_id'   => $order->user_id,
                'label'     => 'Delivery Address',
                'address'   => $uberAddress['street_address'] ?? ($uberAddress['address'] ?? null),
                'apartment' => $uberAddress['apt_suite_unit'] ?? ($uberAddress['apartment'] ?? null),
                'latitude'  => $uberAddress['latitude']  ?? null,
                'longitude' => $uberAddress['longitude'] ?? null,
            ]);
        }

        // ========== CREATE ORDER ITEMS ==========
        $uberItems = $uberCart['items'] ?? [];
        
        if (!empty($uberItems)) {
            // Get taxes from database
            $taxes = AppLibrary::pluck(Tax::get(), 'obj', 'id');
            
            $itemsArray = [];
            $totalTax = 0;
            $i = 0;
            
            foreach ($uberItems as $uberItem) {
                // Get item details from Uber
                $itemName = $uberItem['name'] ?? $uberItem['title'] ?? 'Unknown Item';
                $itemQuantity = $uberItem['quantity'] ?? 1;
                $itemPrice = isset($uberItem['price']) ? $uberItem['price'] / 100 : 0;
                $itemTotalPrice = isset($uberItem['total_price']) ? $uberItem['total_price'] / 100 : ($itemPrice * $itemQuantity);
                $itemDiscount = isset($uberItem['discount']) ? $uberItem['discount'] / 100 : 0;
                
                // Map to internal item ID
                $internalItemId = $this->getInternalItemId($itemName);
                
                // Get tax for this item
                $taxId = $this->getTaxIdForItem($internalItemId);
                $taxRate = isset($taxes[$taxId]) ? $taxes[$taxId]->tax_rate : 0;
                $taxType = isset($taxes[$taxId]) ? $taxes[$taxId]->type : TaxType::FIXED;
                $taxPrice = $taxType === TaxType::FIXED ? $taxRate : ($item->total_price * $taxRate) / 100;
                
                // Build item array - REMOVED 'item_name' column
                $itemsArray[$i] = [
                    'order_id'             => $order->id,
                    'branch_id'            => $branchId,
                    'item_id'              => $internalItemId,
                    'quantity'             => $itemQuantity,
                    'discount'             => (float)$itemDiscount,
                    'tax_rate'             => $taxRate,
                    'tax_type'             => $taxType,
                    'tax_amount'           => $taxPrice,
                    'price'                => $itemPrice,
                    'item_variations'      => json_encode($uberItem['variations'] ?? []),
                    'item_extras'          => json_encode($uberItem['extras'] ?? []),
                    'instruction'          => $uberItem['special_instructions'] ?? null,
                    'item_variation_total' => isset($uberItem['variations_total']) ? $uberItem['variations_total'] / 100 : 0,
                    'item_extra_total'     => isset($uberItem['extras_total']) ? $uberItem['extras_total'] / 100 : 0,
                    'total_price'          => $itemTotalPrice,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ];
                
                $totalTax += $taxPrice;
                $i++;
            }
            
            // Bulk insert order items
            if (!empty($itemsArray)) {
                OrderItem::insert($itemsArray);
                Log::info("[{$source}] Created " . count($itemsArray) . " order items", [
                    'order_id' => $order->id,
                    'total_tax_calculated' => $totalTax
                ]);
            }
            
            // Update order with calculated tax if needed
            if ($totalTax > 0 && $tax == 0) {
                $order->update(['total_tax' => $totalTax]);
            }
        }
        // ========== END OF ORDER ITEMS CREATION ==========

        Log::info("[{$source}] New order stored (pending approval)", [
            'order_id' => $order->id,
            'provider_order_id' => $providerOrderId,
        ]);

        return $order;
    }

    // ========== HELPER METHODS ==========
    
    /**
     * Map Uber item name to your internal item ID
     */
    private function getInternalItemId(string $uberItemName): int
    {
        // Try to find item in database by name
        $item = \App\Models\Item::where('name', $uberItemName)->first();
        if ($item) {
            return $item->id;
        }
        
        // Fallback mapping
        $mapping = [
            'Cheeseburger' => 1,
            'French Fries' => 2,
            'Coke' => 3,
        ];
        
        return $mapping[$uberItemName] ?? 0;
    }
    
    /**
     * Get tax ID for an item
     */
    private function getTaxIdForItem(int $itemId): int
    {
        // Try to get tax from item record
        $item = \App\Models\Item::find($itemId);
        if ($item && isset($item->tax_id)) {
            return $item->tax_id;
        }
        
        // Fallback mapping
        $itemTaxMapping = [
            1 => 1,  // Cheeseburger -> Tax ID 1
            2 => 2,  // French Fries -> Tax ID 2
            3 => 1,  // Coke -> Tax ID 1
        ];
        
        return $itemTaxMapping[$itemId] ?? 0;
    }
    
    // ========== END OF HELPER METHODS ==========

    // -------------------------------------------------------------------------
    // API ENDPOINT: Accept Order (Manually triggered)
    // -------------------------------------------------------------------------

    public function acceptOrder(Request $request, $orderId)
    {
        try {
            $order = Order::where('provider_order_id', $orderId)
                ->orWhere('id', $orderId)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            if ($order->status === OrderStatus::CONFIRMED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already accepted'
                ], 400);
            }

            $accepted = $this->acceptUberOrder($order->provider_order_id);

            if ($accepted) {
                $order->update([
                    'status' => OrderStatus::ACCEPT,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Order accepted successfully',
                    'order_id' => $order->id,
                    'provider_order_id' => $order->provider_order_id
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to accept order with Uber'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('[uber] Accept order API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // API ENDPOINT: Decline Order (Manually triggered)
    // -------------------------------------------------------------------------

    public function declineOrder(Request $request, $orderId)
    {
        try {
            $request->validate([
                'reason' => 'required|in:RESTAURANT_CLOSED,ITEM_UNAVAILABLE,TOO_BUSY,REASON_UNKNOWN,POS_NOT_READY,OTHER'
            ]);

            $order = Order::where('provider_order_id', $orderId)
                ->orWhere('id', $orderId)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            if ($order->status === OrderStatus::CANCELED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already declined'
                ], 400);
            }

            $reason = $request->input('reason', 'REASON_UNKNOWN');
            $declined = $this->declineUberOrder($order->provider_order_id, $reason);

            if ($declined) {
                $order->update([
                    'status' => OrderStatus::CANCELED,
                    'reason' => $reason,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Order declined successfully',
                    'order_id' => $order->id,
                    'provider_order_id' => $order->provider_order_id,
                    'reason' => $reason
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to decline order with Uber'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('[uber] Decline order API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // API ENDPOINT: Get Order Status
    // -------------------------------------------------------------------------

    public function getOrder($orderId)
    {
        $order = Order::where('provider_order_id', $orderId)
            ->orWhere('id', $orderId)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'order_serial_no' => $order->order_serial_no,
                'provider_order_id' => $order->provider_order_id,
                'status' => $order->status,
                'provider_status' => $order->provider_status,
                'total' => $order->total,
                'created_at' => $order->created_at,
                'reason' => $order->reason ?? null
            ]
        ]);
    }

    // -------------------------------------------------------------------------
    // UBER API METHODS
    // -------------------------------------------------------------------------

    private function acceptUberOrder(string $orderId): bool
    {
        try {
            $environment = config('services.uber.environment', 'sandbox');
            $isSandbox = ($environment === 'sandbox');
            
            $token = $this->getUberAccessToken();
            
            $url = $isSandbox 
                ? "https://test-api.uber.com/v1/eats/orders/{$orderId}/accept_pos_order"
                : "https://api.uber.com/v1/eats/orders/{$orderId}/accept_pos_order";

            $response = Http::withToken($token)
                ->post($url, ['reason' => 'ACCEPTED']);

            Log::info('[uber] Accept order response', [
                'order_id' => $orderId,
                'status'   => $response->status(),
                'success'  => $response->successful(),
                'environment' => $environment
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('[uber] Accept order exception: ' . $e->getMessage());
            return false;
        }
    }

    public function declineUberOrder(string $orderId, string $reason = 'REASON_UNKNOWN'): bool
    {
        try {
            $environment = config('services.uber.environment', 'sandbox');
            $isSandbox = ($environment === 'sandbox');
            
            $token = $this->getUberAccessToken();
            
            $url = $isSandbox 
                ? "https://test-api.uber.com/v1/eats/orders/{$orderId}/deny_pos_order"
                : "https://api.uber.com/v1/eats/orders/{$orderId}/deny_pos_order";

            $response = Http::withToken($token)
                ->post($url, ['reason' => $reason]);

            Log::info('[uber] Decline order response', [
                'order_id' => $orderId,
                'reason'   => $reason,
                'status'   => $response->status(),
                'success'  => $response->successful(),
                'environment' => $environment
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('[uber] Decline order exception: ' . $e->getMessage());
            return false;
        }
    }

    private function getUberAccessToken(): string
    {
        $environment = config('services.uber.environment', 'sandbox');
        $isSandbox = ($environment === 'sandbox');
        
        $url = $isSandbox 
            ? 'https://sandbox-login.uber.com/oauth/v2/token'
            : 'https://login.uber.com/oauth/v2/token';

        $response = Http::asForm()->post($url, [
            'client_id'     => config('services.uber.client_id'),
            'client_secret' => config('services.uber.client_secret'),
            'grant_type'    => 'client_credentials',
            'scope'         => 'eats.order',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get Uber access token: ' . $response->body());
        }

        return $response->json()['access_token'];
    }
}