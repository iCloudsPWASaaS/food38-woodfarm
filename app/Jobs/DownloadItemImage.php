<?php

namespace App\Jobs;

use App\Models\Item;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DownloadItemImage implements ShouldQueue
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

    public function __construct(public Item $item) {}

    public function handle(): void
    {
        if (!empty($this->item->getFirstMediaUrl('item'))) {
            return;
        }

        $imageUrl =
            $this->tryOpenFoodFacts()
            ?? $this->trySpoonacular()
            ?? $this->tryDuckDuckGo();

        if (!$imageUrl) {
            Log::warning("No image found for item #{$this->item->id} [{$this->item->name}]");
            return;
        }

        $mediaName = Str::slug($this->item->name, '_');
        $extension = $this->resolveExtension($imageUrl);

        try {
            $this->item
                ->addMediaFromUrl($imageUrl)
                ->usingName($mediaName)
                ->usingFileName("{$mediaName}.{$extension}")
                ->toMediaCollection('item');
        } catch (\Throwable $e) {
            Log::warning("Failed downloading image for item #{$this->item->id} [{$this->item->name}]: {$e->getMessage()}");
            throw $e;
        }
    }

    private function tryOpenFoodFacts(): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'FoodKingBot/1.0'])
                ->get('https://world.openfoodfacts.org/cgi/search.pl', [
                    'search_terms'  => $this->item->name,
                    'search_simple' => 1,
                    'action'        => 'process',
                    'json'          => 1,
                    'page_size'     => 5,
                    'fields'        => 'image_front_url,image_url,product_name',
                ]);

            if ($response->failed()) return null;

            foreach ($response->json('products') ?? [] as $product) {
                $url = $product['image_front_url'] ?? $product['image_url'] ?? null;
                if ($url && str_starts_with($url, 'http')) return $url;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function trySpoonacular(): ?string
    {
        $apiKey = (string) config('services.spoonacular.api_key', '');
        if ($apiKey === '') return null;

        try {
            $response = Http::timeout(10)
                ->get('https://api.spoonacular.com/food/products/search', [
                    'apiKey' => $apiKey,
                    'query'  => $this->item->name,
                    'number' => 3,
                ]);

            if ($response->failed()) return null;

            foreach ($response->json('products') ?? [] as $product) {
                $url = $product['image'] ?? null;
                if (!$url) continue;

                if (!str_starts_with($url, 'http')) {
                    $url = 'https://spoonacular.com/productImages/' . $url;
                }

                return $url;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function tryDuckDuckGo(): ?string
    {
        if (Cache::get('ddg_blocked')) return null;

        $userAgent = self::USER_AGENTS[array_rand(self::USER_AGENTS)];
        $query     = $this->item->name . ' food';

        sleep(rand(3, 7));

        $vqd = $this->getDdgVqd($query, $userAgent);
        if (!$vqd) return null;

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
                if (preg_match('/vqd=([\d-]+)/', $body, $m)) return $m[1];
                if (preg_match('/"vqd":"([^"]+)"/', $body, $m)) return $m[1];

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
                return null;
            }

            if ($response->failed()) return null;

            $results = $response->json('results') ?? [];
            if (empty($results)) return null;

            $top = array_slice($results, 0, min(5, count($results)));
            shuffle($top);

            foreach ($top as $result) {
                $url = $result['image'] ?? null;
                if ($url && $this->isReachable($url, $userAgent)) return $url;
            }

            return null;
        } catch (\Throwable $e) {
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

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
    }

    public function failed(\Throwable $e): void
    {
        Log::error("Failed item image job for item #{$this->item->id} [{$this->item->name}]: {$e->getMessage()}");
    }
}
