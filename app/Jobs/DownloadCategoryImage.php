<?php

namespace App\Jobs;

use App\Models\ItemCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DownloadCategoryImage implements ShouldQueue
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

    public function __construct(public ItemCategory $category) {}

    public function handle(): void
    {
        $imageUrl =
            $this->tryUnsplash()
            ?? $this->tryPexels()
            ?? $this->tryDuckDuckGo();

        if (!$imageUrl) {
            Log::warning("No image found for category #{$this->category->id} [{$this->category->name}]");
            return;
        }

        $mediaName = Str::slug($this->category->name, '_');
        $extension = $this->resolveExtension($imageUrl);

        // FIX: append category ID to filename so categories with similar names
        // (e.g. "Beer" and "Beer & Cider" both slug to "beer_") never overwrite
        // each other in Spatie's media library.
        $this->category
            ->addMediaFromUrl($imageUrl)
            ->usingName($mediaName)
            ->usingFileName("{$mediaName}_{$this->category->id}.{$extension}")
            ->toMediaCollection('item-category');

        Log::info("✅ Category #{$this->category->id} [{$this->category->name}] → {$mediaName}_{$this->category->id}.{$extension}");
    }

    // -------------------------------------------------------------------------
    // Source 1: Unsplash
    // Requires UNSPLASH_ACCESS_KEY in services config.
    // 50 requests/hour on free tier.
    // FIX: shuffle results so similar category names don't always get the same
    //      #1 ranked photo.
    // -------------------------------------------------------------------------
    private function tryUnsplash(): ?string
    {
        try {
            $response = Http::timeout(10)
                ->get('https://api.unsplash.com/search/photos', [
                    'client_id'   => config('services.unsplash.api_key'),
                    'query'       => $this->category->name,
                    'per_page'    => 5,
                    'orientation' => 'portrait',
                ]);

            if ($response->failed()) return null;

            $results = collect($response->json('results') ?? [])
                ->filter(fn($item) => !empty($item['urls']['regular']) || !empty($item['urls']['full']))
                ->shuffle();

            foreach ($results as $item) {
                $url = $item['urls']['regular'] ?? $item['urls']['full'] ?? null;
                if ($url && str_starts_with($url, 'http')) {
                    Log::debug("Unsplash hit: category #{$this->category->id} [{$this->category->name}]");
                    return $url;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug("Unsplash failed [{$this->category->name}]: {$e->getMessage()}");
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Source 2: Pexels
    // Requires PEXELS_API_KEY in services config.
    // 200 requests/hour, 20,000/month free.
    // FIX: shuffle results for same reason as Unsplash above.
    // -------------------------------------------------------------------------
    private function tryPexels(): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => config('services.pexels.api_key'),
                ])
                ->get('https://api.pexels.com/v1/search', [
                    'query'       => $this->category->name,
                    'per_page'    => 5,
                    'orientation' => 'portrait',
                ]);

            if ($response->failed()) return null;

            $photos = collect($response->json('photos') ?? [])
                ->filter(fn($item) => !empty($item['src']))
                ->shuffle();

            foreach ($photos as $item) {
                $url = $item['src']['large2x'] ?? $item['src']['large'] ?? $item['src']['original'] ?? null;
                if ($url && str_starts_with($url, 'http')) {
                    Log::debug("Pexels hit: category #{$this->category->id} [{$this->category->name}]");
                    return $url;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug("Pexels failed [{$this->category->name}]: {$e->getMessage()}");
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

        // FIX: removed " category" suffix — it made all queries resolve to the
        //      same generic stock-photo results.
        $query = $this->category->name . ' product photography';

        sleep(rand(3, 7));

        $vqd = $this->getDdgVqd($query, $userAgent);

        if (!$vqd) {
            Log::debug("DDG: no VQD for [{$this->category->name}]");
            return null;
        }

        sleep(rand(1, 3));

        return $this->getDdgImage($query, $vqd, $userAgent);
    }

    private function getDdgVqd(string $query, string $userAgent): ?string
    {
        // FIX: include category ID in cache key so each category gets its own
        //      VQD token. The old shared key caused all jobs to reuse the same
        //      DDG session and return identical result sets. Short TTL so
        //      retries still benefit from caching without polluting other jobs.
        $cacheKey = 'ddg_vqd_' . md5($query) . '_' . $this->category->id;

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($query, $userAgent) {
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
                // FIX: forget the per-category key, not the old shared one.
                Cache::forget('ddg_vqd_' . md5($query) . '_' . $this->category->id);
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
                    Log::debug("DDG hit: category #{$this->category->id} [{$this->category->name}]");
                    return $url;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug("DDG image fetch failed [{$this->category->name}]: {$e->getMessage()}");
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
        Log::error("❌ Failed category #{$this->category->id} [{$this->category->name}]: {$e->getMessage()}");
    }
}