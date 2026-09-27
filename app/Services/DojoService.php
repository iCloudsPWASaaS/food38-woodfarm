<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;

class DojoService
{
    private string $env;
    private ?string $host;
    private ?string $apiKey;
    private ?string $locationMid;
    private ?string $tidPrimary;
    private ?string $tidSecondary;
    private Client $http;

    public function __construct()
    {
        $this->env          = env('DOJO_ENV', 'local');
        $this->host         = env('DOJO_HOST');
        $this->apiKey       = env('DOJO_API_KEY');
        $this->locationMid  = env('DOJO_LOCATION_MID');
        $this->tidPrimary   = env('DOJO_TID_PRIMARY');
        $this->tidSecondary = env('DOJO_TID_SECONDARY');
        $this->http         = new Client(['timeout' => 30]);
    }

    /**
     * Initiate a payment on the terminal.
     * Returns immediately with status=pending and a transactionId.
     * The terminal will wake up and display the amount to the customer.
     * Poll getTransaction() until status changes from pending.
     */
    public function createTerminalTransaction(
        float $amount,
        ?string $reference = null,
        ?string $terminalId = null,
        ?string $simulate = null
    ): array {
        // ── Local / mock mode ────────────────────────────────────────────────
        if ($this->env === 'local') {
            return [
                'transactionId' => (string) Str::uuid(),
                'status'        => 'pending',
                'amount'        => $amount,
                'reference'     => $reference,
                'terminalId'    => $terminalId ?: $this->tidPrimary,
                'mode'          => 'local',
            ];
        }

        // ── Live mode ────────────────────────────────────────────────────────
        $tid = $terminalId ?: $this->tidPrimary;
        $url = $this->baseUrl() . '/pac/terminals/' . $tid . '/transactions';

        $payload = [
            'amount'    => (int) round($amount * 100), // Paymentsense expects pence
            'reference' => $reference ?? (string) Str::uuid(),
        ];

        try {
            $res  = $this->http->post($url, [
                'headers' => $this->buildHeaders(),
                'json'    => $payload,
            ]);
            $body = json_decode((string) $res->getBody(), true) ?: [];

            // Paymentsense returns requestId — normalise to transactionId
            return [
                'transactionId' => $body['requestId'] ?? $body['transactionId'] ?? null,
                'location'      => $body['location'] ?? null,
                'status'        => 'pending', // always pending at this stage
                'amount'        => $amount,
                'reference'     => $reference,
                'terminalId'    => $tid,
                'mode'          => $this->env,
            ];
        } catch (RequestException $e) {
            $errorBody = $e->hasResponse()
                ? json_decode((string) $e->getResponse()->getBody(), true)
                : [];
            return [
                'transactionId' => null,
                'status'        => 'error',
                'message'       => $errorBody['message'] ?? $e->getMessage(),
                'terminalId'    => $tid,
                'mode'          => $this->env,
            ];
        }
    }

    /**
     * Poll this after createTerminalTransaction().
     * Call every 3 seconds until status is no longer 'pending'.
     * Possible final statuses: approved, declined, cancelled, failed, timeout
     */
    public function getTransaction(string $terminalId, string $transactionId): array
    {
        // ── Local / mock mode ────────────────────────────────────────────────
        if ($this->env === 'local') {
            return [
                'transactionId' => $transactionId,
                'status'        => 'approved',
                'terminalId'    => $terminalId,
                'mode'          => 'local',
            ];
        }

        // ── Live mode ────────────────────────────────────────────────────────
        $tid = $terminalId ?: $this->tidPrimary;
        $url = $this->baseUrl() . '/pac/terminals/' . $tid . '/transactions/' . $transactionId;

        try {
            $res  = $this->http->get($url, ['headers' => $this->buildHeaders()]);
            $body = json_decode((string) $res->getBody(), true) ?: [];

            // Normalise status — Paymentsense may use different casing/field names
            $rawStatus = strtolower(
                $body['status'] ??
                $body['transactionStatus'] ??
                $body['paymentStatus'] ??
                'pending'
            );

            return [
                'transactionId' => $transactionId,
                'status'        => $rawStatus,
                'terminalId'    => $tid,
                'mode'          => $this->env,
                'raw'           => $body,
            ];
        } catch (RequestException $e) {
            $errorBody = $e->hasResponse()
                ? json_decode((string) $e->getResponse()->getBody(), true)
                : [];
            return [
                'transactionId' => $transactionId,
                'status'        => 'error',
                'message'       => $errorBody['message'] ?? $e->getMessage(),
                'terminalId'    => $tid,
                'mode'          => $this->env,
            ];
        }
    }

    /**
     * Cancel an in-progress transaction on the terminal.
     */
    public function cancelTransaction(string $terminalId, string $transactionId): array
    {
        if ($this->env === 'local') {
            return [
                'transactionId' => $transactionId,
                'status'        => 'cancelled',
                'mode'          => 'local',
            ];
        }

        $tid = $terminalId ?: $this->tidPrimary;
        $url = $this->baseUrl() . '/pac/terminals/' . $tid . '/transactions/' . $transactionId . '/cancel';

        try {
            $res  = $this->http->post($url, ['headers' => $this->buildHeaders()]);
            $body = json_decode((string) $res->getBody(), true) ?: [];

            return $body + [
                'transactionId' => $transactionId,
                'terminalId'    => $tid,
                'mode'          => $this->env,
            ];
        } catch (RequestException $e) {
            $errorBody = $e->hasResponse()
                ? json_decode((string) $e->getResponse()->getBody(), true)
                : [];
            return [
                'transactionId' => $transactionId,
                'status'        => 'error',
                'message'       => $errorBody['message'] ?? $e->getMessage(),
                'terminalId'    => $tid,
                'mode'          => $this->env,
            ];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function baseUrl(): string
    {
        return rtrim((string) $this->host, '/');
    }

    private function buildHeaders(): array
    {
        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Basic ' . base64_encode('x:' . (string) $this->apiKey),
        ];

        if (!empty($this->locationMid)) {
            $headers['Dojo-Location-MID'] = $this->locationMid;
        }

        return $headers;
    }
}