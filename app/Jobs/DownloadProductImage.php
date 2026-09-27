<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DownloadProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $backoff = 60;

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ];

    public function __construct(public Product $product) {}

    public function handle(): void
    {
        $imageUrl =
            $this->tryOpenFoodFacts()  // 1. Free, no key — best for branded food & drink
            ?? $this->trySpoonacular() // 2. Spoonacular — catches what OFF misses
            ?? $this->tryDuckDuckGo(); // 3. DDG scrape — last resort

        if (!$imageUrl) {
            Log::warning("No image found for #{$this->product->id} [{$this->product->name}]");
            return;
        }

        $mediaName = Str::slug($this->product->name, '_');
        $extension = $this->resolveExtension($imageUrl);

        $this->product
            ->addMediaFromUrl($imageUrl)
            ->usingName($mediaName)
            ->usingFileName("{$mediaName}.{$extension}")
            ->toMediaCollection('product');

        Log::info("✅ #{$this->product->id} [{$this->product->name}] → {$mediaName}.{$extension}");
    }

    // -------------------------------------------------------------------------
    // Source 1: Open Food Facts
    // Free, no key. Best for UK/EU branded food & drink.
    // -------------------------------------------------------------------------
    private function tryOpenFoodFacts(): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'YourShopBot/1.0 (contact@yourshop.com)'])
                ->get('https://world.openfoodfacts.org/cgi/search.pl', [
                    'search_terms'  => $this->product->name,
                    'search_simple' => 1,
                    'action'        => 'process',
                    'json'          => 1,
                    'page_size'     => 5,
                    'fields'        => 'image_front_url,image_url,product_name',
                ]);

            if ($response->failed()) return null;

            foreach ($response->json('products') ?? [] as $item) {
                $url = $item['image_front_url'] ?? $item['image_url'] ?? null;
                if ($url && str_starts_with($url, 'http')) {
                    Log::debug("OFF hit: #{$this->product->id} [{$this->product->name}]");
                    return $url;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug("OFF failed [{$this->product->name}]: {$e->getMessage()}");
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Source 2: Spoonacular
    // Key is hardcoded from your provided key.
    // 150 points/day free — used only when OFF returns nothing.
    // -------------------------------------------------------------------------
    private function trySpoonacular(): ?string
    {
        try {
            $response = Http::timeout(10)
                ->get('https://api.spoonacular.com/food/products/search', [
                    'apiKey' => config('services.spoonacular.api_key'),
                    'query'  => $this->product->name,
                    'number' => 3,
                ]);

            if ($response->failed()) return null;

            foreach ($response->json('products') ?? [] as $item) {
                $url = $item['image'] ?? null;
                if ($url) {
                    if (!str_starts_with($url, 'http')) {
                        $url = 'https://spoonacular.com/productImages/' . $url;
                    }
                    Log::debug("Spoonacular hit: #{$this->product->id} [{$this->product->name}]");
                    return $url;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug("Spoonacular failed [{$this->product->name}]: {$e->getMessage()}");
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Source 3: DuckDuckGo scrape
    // No key. Last resort only — slower and fragile by nature.
    // Skipped automatically for 10 min if a 403 is received.
    // -------------------------------------------------------------------------
    private function tryDuckDuckGo(): ?string
    {
        if (Cache::get('ddg_blocked')) {
            Log::debug("DDG skipped — currently rate-limited");
            return null;
        }

        $userAgent = self::USER_AGENTS[array_rand(self::USER_AGENTS)];
        $query     = $this->product->name . ' product';

        sleep(rand(3, 7));

        $vqd = $this->getDdgVqd($query, $userAgent);

        if (!$vqd) {
            Log::debug("DDG: no VQD for [{$this->product->name}]");
            return null;
        }

        sleep(rand(1, 3));

        return $this->getDdgImage($query, $vqd, $userAgent);
    }

    private function getDdgVqd(string $query, string $userAgent): ?string
    {
        $cacheKey = 'ddg_vqd_' . md5($query);

        return Cache::remember($cacheKey, now()->addMinutes(55), function () use ($query, $userAgent) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent'                => $userAgent,
                        'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language'           => 'en-GB,en;q=0.9',
                        'Accept-Encoding'           => 'gzip, deflate, br',
                        'DNT'                       => '1',
                        'Connection'                => 'keep-alive',
                        'Upgrade-Insecure-Requests' => '1',
                    ])
                    ->get('https://duckduckgo.com/', ['q' => $query]);

                if ($response->failed()) return null;

                $body = $response->body();

                if (preg_match('/vqd=(["\'])([^"\']+)\1/', $body, $m)) return $m[2];
                if (preg_match('/vqd=([\d-]+)/',           $body, $m)) return $m[1];
                if (preg_match('/"vqd":"([^"]+)"/',        $body, $m)) return $m[1];

                return null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    private function getDdgImage(string $query, string $vqd, string $userAgent): ?string
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent'       => $userAgent,
                    'Accept'           => 'application/json, text/javascript, */*; q=0.01',
                    'Accept-Language'  => 'en-GB,en;q=0.9',
                    'Accept-Encoding'  => 'gzip, deflate, br',
                    'Referer'          => 'https://duckduckgo.com/',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'DNT'              => '1',
                    'Sec-Fetch-Dest'   => 'empty',
                    'Sec-Fetch-Mode'   => 'cors',
                    'Sec-Fetch-Site'   => 'same-origin',
                ])
                ->get('https://duckduckgo.com/i.js', [
                    'q'   => $query,
                    'o'   => 'json',
                    'vqd' => $vqd,
                    'f'   => ',,,,,',
                    'p'   => '1',
                    'l'   => 'uk-en',
                ]);

            if ($response->status() === 403) {
                Cache::put('ddg_blocked', true, now()->addMinutes(10));
                Cache::forget('ddg_vqd_' . md5($query));
                Log::warning("DDG: 403 — blocked for 10 minutes");
                return null;
            }

            if ($response->failed()) return null;

            $results = $response->json('results') ?? [];
            if (empty($results)) return null;

            $top = array_slice($results, 0, min(5, count($results)));
            shuffle($top);

            foreach ($top as $result) {
                $url = $result['image'] ?? null;
                if ($url && $this->isReachable($url, $userAgent)) {
                    Log::debug("DDG hit: #{$this->product->id} [{$this->product->name}]");
                    return $url;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug("DDG image fetch failed [{$this->product->name}]: {$e->getMessage()}");
            return null;
        }
    }

    private function isReachable(string $url, string $userAgent): bool
    {
        try {
            return Http::timeout(8)
                ->withHeaders(['User-Agent' => $userAgent])
                ->head($url)
                ->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $ext  = explode('?', $ext)[0];

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? $ext : 'jpg';
    }

    public function failed(\Throwable $e): void
    {
        Log::error("❌ Failed #{$this->product->id} [{$this->product->name}]: {$e->getMessage()}");
    }
}