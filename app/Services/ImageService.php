<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Convert an image to WebP and save it.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $directory
     * @return string|null The relative path to the saved image.
     */
    public function convertToWebp($file, $directory = 'products')
    {
        $extension = $file->getClientOriginalExtension();
        $sourcePath = $file->getRealPath();

        // Create image resource from source
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $image = imagecreatefrompng($sourcePath);
                // Handle transparency for PNG
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            case 'webp':
                $image = imagecreatefromwebp($sourcePath);
                break;
            default:
                // If it's another format, we might not support it via GD
                return null;
        }

        if (!$image) {
            return null;
        }

        $filename = Str::random(40) . '.webp';

        // Capture output to variable
        ob_start();
        imagewebp($image, null, 80); // 80 quality for good balance
        $content = ob_get_clean();
        imagedestroy($image);

        $path = $directory . '/' . $filename;

        // Save using Storage facade
        Storage::disk('public')->put($path, $content);

        return $path;
    }

    /**
     * Delete an image from storage using its public URL or relative path.
     *
     * @param string|null $imagePath
     * @return void
     */
    public function deleteImage($imagePath)
    {
        if (empty($imagePath)) {
            return;
        }

        // Extract relative path if it's a full URL
        // Example: http://localhost:8000/storage/products/xyz.webp -> products/xyz.webp
        $relativePath = $imagePath;
        if (str_contains($imagePath, '/storage/')) {
            $relativePath = explode('/storage/', $imagePath)[1];
        }

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
