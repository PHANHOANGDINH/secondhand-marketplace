<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A public picture of a person or a shop. The upload is decoded and redrawn as a small square JPEG, so
 * metadata such as the GPS position of a phone photo and anything hidden in the file never reach the public disk.
 */
class SquarePicture
{
    private const SIZE = 512;

    private const MAX_PIXELS = 20000000;

    /** Folders this class writes to: only files in them may be deleted. */
    private const FOLDERS = ['avatars/', 'shop-logos/'];

    /** Returns the path of the stored picture under the given folder ("avatars/12"). */
    public function store(UploadedFile $file, string $folder): string
    {
        $path = $folder.'/'.Str::uuid().'.jpg';
        if (! Storage::disk('public')->put($path, $this->redraw($file))) {
            throw new \RuntimeException('Picture storage failed.');
        }

        return $path;
    }

    /** Called after the commit: a failed delete leaves a stray file, never a broken picture. */
    public function discard(?string $path): void
    {
        if (! $path || ! Str::startsWith($path, self::FOLDERS)) {
            return;
        }
        try {
            Storage::disk('public')->delete($path);
        } catch (Throwable $exception) {
            Log::warning('Old picture could not be deleted.', ['exception_type' => $exception::class]);
        }
    }

    private function redraw(UploadedFile $file): string
    {
        $size = @getimagesize($file->getRealPath());
        $limit = ini_parse_quantity(ini_get('memory_limit'));
        $estimate = $size ? $size[0] * $size[1] * 8 + 16777216 : PHP_INT_MAX;
        if (! $size || $size[0] * $size[1] > self::MAX_PIXELS || ($limit > 0 && memory_get_usage(true) + $estimate > $limit)) {
            throw ValidationException::withMessages(['avatar' => ['This image exceeds the pixel or processing memory limit.']]);
        }
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages(['avatar' => ['The image could not be decoded.']]);
        }
        $source = $this->upright($source, $file);
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        // Transparent parts become white: JPEG has no alpha.
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, (int) (($width - $side) / 2), (int) (($height - $side) / 2), self::SIZE, self::SIZE, $side, $side);
        ob_start();
        try {
            $encoded = imagejpeg($canvas, null, 85);
            $clean = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($source);
            imagedestroy($canvas);
        }
        if (! $encoded || ! $clean) {
            throw ValidationException::withMessages(['avatar' => ['The image could not be encoded safely.']]);
        }

        return $clean;
    }

    /** Phones store photos sideways plus a rotation flag; redrawing drops the flag, so apply it first. */
    private function upright(\GdImage $image, UploadedFile $file): \GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }
        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if (! $rotated) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }
}
