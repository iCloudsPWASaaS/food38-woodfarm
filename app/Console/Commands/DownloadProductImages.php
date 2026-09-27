<?php

namespace App\Console\Commands;

use App\Jobs\DownloadProductImage;
use App\Models\Product;
use Illuminate\Console\Command;

class DownloadProductImages extends Command
{
    protected $signature   = 'products:download-images';
    protected $description = 'Dispatch image download jobs for all products without images';

    public function handle(): void
    {
        $this->info('Command started...');

        $total = Product::whereDoesntHave('media')
            ->whereNotNull('sku')
            ->count();

        if ($total === 0) {
            $this->info('All products already have images!');
            return;
        }

        $this->info("Found {$total} products without images. Dispatching jobs...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Product::whereDoesntHave('media')
            ->whereNotNull('sku')
            ->chunkById(100, function ($products) use ($bar) {
                foreach ($products as $product) {
                    DownloadProductImage::dispatch($product)->onQueue('images');
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info('All jobs dispatched!');
        $this->info('Now run: php artisan queue:work --queue=images --tries=3 --sleep=1 --stop-when-empty');
    }
}
