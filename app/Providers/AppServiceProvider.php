<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('filesystems.uploads_disk') !== 'spaces') {
            return;
        }

        $d = config('filesystems.disks.spaces', []);
        $missing = [];
        if (empty($d['key'])) {
            $missing[] = 'DO_SPACES_KEY';
        }
        if (empty($d['secret'])) {
            $missing[] = 'DO_SPACES_SECRET';
        }
        if (empty($d['bucket'])) {
            $missing[] = 'DO_SPACES_BUCKET';
        }
        if (empty($d['endpoint'])) {
            $missing[] = 'DO_SPACES_ENDPOINT';
        }
        if ($missing !== []) {
            Log::warning(
                'FILESYSTEM_UPLOADS_DISK is "spaces" but Spaces config is incomplete. Uploads will fail until you set: '.implode(', ', $missing),
                ['missing' => $missing]
            );
        }

        if (empty($d['url'])) {
            Log::warning(
                'DO_SPACES_URL is empty. Set it to your public origin, e.g. https://your-space-name.nyc3.digitaloceanspaces.com so image URLs work in the API.'
            );
        }
    }
}
