<?php

namespace App\Console\Commands;

use App\Jobs\DownloadItemImage;
use App\Models\Item;
use Illuminate\Console\Command;

class DownloadItemImages extends Command
{
    protected $signature   = 'items:download-images';
    protected $description = 'Dispatch image download jobs for all items without images';

    public function handle(): void
    {
        $this->info('Command started...');

        $total = Item::whereDoesntHave('media')->count();

        if ($total === 0) {
            $this->info('All items already have images!');
            return;
        }

        $this->info("Found {$total} items without images. Dispatching jobs...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Item::whereDoesntHave('media')
            ->chunkById(100, function ($items) use ($bar) {
                foreach ($items as $item) {
                    DownloadItemImage::dispatch($item)->onQueue('images');
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info('All jobs dispatched!');
        $this->info('Now run: php artisan queue:work --queue=images --tries=3 --sleep=1 --stop-when-empty');
    }
}
