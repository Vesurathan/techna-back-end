<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Central upload disk: local "public" for dev, DigitalOcean Spaces (S3 driver) in production.
 * DB stores object keys (e.g. gallery/1/photo.jpg); use publicUrl() for API responses.
 */
final class MediaDisk
{
    public static function diskName(): string
    {
        return config('filesystems.uploads_disk', 'public');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /** @return string Storage key relative to disk root */
    public static function storeUpload(UploadedFile $file, string $directory): string
    {
        $name = self::diskName();
        $driver = config("filesystems.disks.{$name}.driver");

        if ($driver === 's3') {
            return $file->storePublicly($directory, $name);
        }

        return $file->store($directory, $name);
    }

    public static function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = trim($path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $key = ltrim($path, '/');
        $url = self::disk()->url($key);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim(config('app.url'), '/').'/'.ltrim($url, '/');
    }

    public static function deleteIfExists(?string $key): void
    {
        if ($key === null || $key === '') {
            return;
        }

        $key = ltrim($key, '/');

        if (self::disk()->exists($key)) {
            self::disk()->delete($key);
        }
    }

    /**
     * Turn a stored public URL (or legacy /storage/ path) back into a disk key for deletion.
     */
    public static function keyFromStoredUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $url = trim($url);

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $u = ltrim($url, '/');
            if (str_starts_with($u, 'storage/')) {
                return substr($u, strlen('storage/')) ?: null;
            }

            return $u ?: null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? $path : null;
    }
}
