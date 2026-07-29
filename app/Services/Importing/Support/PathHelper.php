<?php

namespace App\Services\Importing\Support;

class PathHelper
{
    /**
     * Normalize a path: replace backslashes with forward slashes,
     * collapse duplicate separators, and remove trailing slashes.
     */
    public static function normalize(string $path): string
    {
        // Replace backslashes with forward slashes
        $path = str_replace('\\', '/', $path);

        // Collapse duplicate separators
        $path = (string) preg_replace('#/+#', '/', $path);

        // Remove trailing slash (unless it's the root)
        return rtrim($path, '/') ?: '/';
    }

    /**
     * Safely join path segments using forward slashes.
     * Prevents directory traversal.
     */
    public static function join(string ...$segments): string
    {
        $parts = [];

        foreach ($segments as $segment) {
            $segment = self::normalize($segment);

            // Remove directory traversal attempts
            $segment = str_replace('../', '', $segment);
            $segment = str_replace('..', '', $segment);

            $parts[] = $segment;
        }

        return self::normalize(implode('/', $parts));
    }

    /**
     * Check if a filename has an image extension (case-insensitive).
     */
    public static function isImageFile(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'tif'], true);
    }

    /**
     * Sanitize a filename for storage: keep Unicode/Arabic, remove traversal.
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove directory traversal
        $filename = str_replace(['../', '..\\', '..'], '', $filename);

        // Remove null bytes
        $filename = str_replace("\0", '', $filename);

        // Trim whitespace
        return trim($filename);
    }

    /**
     * Resolve a path relative to a base, ensuring no escape via traversal.
     * Returns null if the resolved path would escape the base.
     */
    public static function resolveSecure(string $base, string $relative): ?string
    {
        $base = self::normalize(realpath($base) ?: $base);

        // Reject any path containing traversal sequences
        if (str_contains($relative, '..')) {
            return null;
        }

        $resolved = self::normalize($base.'/'.$relative);

        // Ensure the resolved path starts with the base
        if (! str_starts_with($resolved, $base)) {
            return null;
        }

        return $resolved;
    }
}
