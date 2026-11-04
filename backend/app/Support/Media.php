<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** Accès aux fichiers (bucket S3 en production, disque local en développement). */
class Media
{
    public static function disk(): string
    {
        return config('filesystems.media_disk');
    }

    public static function storage(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(self::disk());
    }

    public static function url(?string $path, int $minutes = 60): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            return self::storage()->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            return self::storage()->url($path);
        }
    }

    public static function put(string $path, string $contents, string $mime = 'image/jpeg'): string
    {
        self::storage()->put($path, $contents, ['ContentType' => $mime]);

        return $path;
    }

    public static function get(string $path): string
    {
        return self::storage()->get($path);
    }

    public static function supportsSignedUploads(): bool
    {
        return config('filesystems.disks.'.self::disk().'.driver') === 's3';
    }

    /** URL signée pour un PUT direct depuis l'appli. */
    public static function uploadUrl(string $path, string $contentType): array
    {
        $r = self::storage()->temporaryUploadUrl($path, now()->addMinutes(20), ['ContentType' => $contentType]);

        return ['upload_url' => $r['url'], 'headers' => array_map(fn ($v) => is_array($v) ? $v[0] : $v, $r['headers'])];
    }
}
