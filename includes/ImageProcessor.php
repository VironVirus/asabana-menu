<?php
declare(strict_types=1);

final class ImageProcessor
{
    private const MAX_UPLOAD_BYTES = 8_388_608;
    private const MAX_PIXELS = 40_000_000;
    private const MAIN_MAX_DIMENSION = 1600;
    private const THUMB_MAX_DIMENSION = 480;

    private string $basePath;
    private string $uploadRoot;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->uploadRoot = $basePath . '/uploads/menu';
    }

    public function process(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Please choose an image to upload.');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The image upload failed. Please try again.');
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($size < 1 || $size > self::MAX_UPLOAD_BYTES || !is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('Image must be a valid upload no larger than 8 MB.');
        }

        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Image processing is unavailable on this server. Enable the PHP GD extension before uploading.');
        }

        if (!class_exists('finfo')) {
            throw new RuntimeException('Secure image validation is unavailable on this server.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($temporaryPath);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('Only JPEG, PNG, and WebP images are accepted.');
        }

        $details = @getimagesize($temporaryPath);
        $width = (int) ($details[0] ?? 0);
        $height = (int) ($details[1] ?? 0);

        if ($width < 1 || $height < 1 || ($width * $height) > self::MAX_PIXELS) {
            throw new RuntimeException('The image dimensions are invalid or too large.');
        }

        $source = $this->loadImage($temporaryPath, $mime);
        $source = $this->orientImage($source, $temporaryPath, $mime);
        $directory = $this->uploadRoot . '/' . gmdate('Y/m');
        $this->ensureDirectory($directory);
        $token = bin2hex(random_bytes(16));
        $useWebp = function_exists('imagewebp');
        $extension = $useWebp ? 'webp' : 'jpg';
        $mainAbsolute = $directory . '/' . $token . '.' . $extension;
        $thumbAbsolute = $directory . '/' . $token . '-thumb.' . $extension;

        try {
            $this->writeVariant($source, $mainAbsolute, self::MAIN_MAX_DIMENSION, $useWebp, 760_000);
            $this->writeVariant($source, $thumbAbsolute, self::THUMB_MAX_DIMENSION, $useWebp, 140_000);
        } catch (Throwable $exception) {
            @unlink($mainAbsolute);
            @unlink($thumbAbsolute);
            throw $exception;
        }

        @chmod($mainAbsolute, 0644);
        @chmod($thumbAbsolute, 0644);

        return [
            'image' => $this->relativePath($mainAbsolute),
            'thumb' => $this->relativePath($thumbAbsolute),
        ];
    }

    public function deleteManagedImages(?string $image, ?string $thumb): void
    {
        foreach ([$image, $thumb] as $relative) {
            if (!is_string($relative) || !str_starts_with($relative, 'uploads/menu/')) {
                continue;
            }

            $absolute = $this->basePath . '/' . ltrim($relative, '/');
            $realDirectory = realpath(dirname($absolute));
            $realRoot = realpath($this->uploadRoot);

            if ($realDirectory !== false && $realRoot !== false && str_starts_with($realDirectory . '/', $realRoot . '/') && is_file($absolute)) {
                @unlink($absolute);
            }
        }
    }

    private function loadImage(string $path, string $mime)
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('The uploaded image could not be decoded safely.');
        }

        return $image;
    }

    private function orientImage($image, string $path, string $mime)
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated !== false) {
            return $rotated;
        }

        return $image;
    }

    private function writeVariant($source, string $destination, int $maxDimension, bool $webp, int $targetBytes): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            throw new RuntimeException('Unable to allocate memory for image processing.');
        }

        if ($webp) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        }

        if (!imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
            throw new RuntimeException('Unable to resize the uploaded image.');
        }

        $qualities = $webp ? [80, 74, 68, 62] : [82, 76, 70, 64];
        $written = false;

        foreach ($qualities as $quality) {
            $written = $webp ? imagewebp($canvas, $destination, $quality) : imagejpeg($canvas, $destination, $quality);

            if (!$written) {
                break;
            }

            clearstatcache(true, $destination);
            if ((int) filesize($destination) <= $targetBytes) {
                break;
            }
        }

        if (!$written || !is_file($destination)) {
            throw new RuntimeException('Unable to compress the uploaded image.');
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the image upload directory.');
        }
    }

    private function relativePath(string $absolute): string
    {
        return ltrim(str_replace($this->basePath, '', $absolute), '/');
    }
}
