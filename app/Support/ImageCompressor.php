<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class ImageCompressor
{
    private const MAX_DIMENSION = 1920;

    private const JPEG_QUALITY = 82;

    private const TARGET_BYTES = 1_500_000;

    public static function shouldCompress(UploadedFile $file): bool
    {
        if (! str_starts_with($file->getMimeType(), 'image/')) {
            return false;
        }

        if ($file->getMimeType() === 'image/gif') {
            return false;
        }

        return $file->getSize() > self::TARGET_BYTES;
    }

    public static function compress(UploadedFile $file): UploadedFile
    {
        if (! extension_loaded('gd') || ! self::shouldCompress($file)) {
            return $file;
        }

        $path = $file->getRealPath();
        if (! $path) {
            return $file;
        }

        $image = self::loadImage($path, $file->getMimeType());
        if (! $image) {
            return $file;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(self::MAX_DIMENSION / $width, self::MAX_DIMENSION / $height, 1);

        if ($ratio < 1) {
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'nb_img_');
        $quality = self::JPEG_QUALITY;

        do {
            imagejpeg($image, $tmpPath, $quality);
            $size = filesize($tmpPath) ?: PHP_INT_MAX;
            $quality -= 5;
        } while ($size > self::TARGET_BYTES && $quality >= 50);

        imagedestroy($image);

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.jpg';

        return new UploadedFile($tmpPath, $name, 'image/jpeg', null, true);
    }

    private static function loadImage(string $path, string $mime): ?\GdImage
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path) ?: null,
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }
}
