<?php

namespace App\Services;

use Carbon\Carbon;
use Storage;

class S3UrlPresigner
{
    public function getPresignedUrl(string $url): string
    {
        $key = $this->getKeyFromUrl($url);
        if (empty($key)) {
            return '';
        }

        return (string) Storage::disk('s3')->temporaryUrl($key, Carbon::now()->addMinutes(5));
    }

    private function getKeyFromUrl(string $url): string
    {
        $urlWithoutQuery = preg_replace('/\?.*/', '', $url);
        $path = (string) parse_url($urlWithoutQuery, PHP_URL_PATH);
        $key = ltrim($path, '/');
        $bucket = (string) config('filesystems.disks.s3.bucket');

        if ($bucket !== '' && str_starts_with($key, $bucket . '/')) {
            $key = substr($key, strlen($bucket) + 1);
        }

        return $key;
    }
}
