<?php

namespace App\Console\Commands;

use App\Jobs\DownloadCategoryImage;
use App\Models\ItemCategory;
use Illuminate\Console\Command;

class DownloadCategoryImages extends Command
{
    protected $signature   = 'categories:download-images';
    protected $description = 'Dispatch image download jobs for all categories without images';

    public function handle(): void
    {
        $this->info('Command started...');

        $total = ItemCategory::whereDoesntHave('media')
            ->count();

        if ($total === 0) {
            $this->info('All categories already have images!');
            return;
        }

        $this->info("Found {$total} categories without images. Dispatching jobs...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        ItemCategory::whereDoesntHave('media')
            ->chunkById(100, function ($categories) use ($bar) {
                foreach ($categories as $category) {
                    DownloadCategoryImage::dispatch($category)->onQueue('images');
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info('All jobs dispatched!');
        $this->info('Now run: php artisan queue:work --queue=images --tries=3 --sleep=1 --stop-when-empty');
    }
}
