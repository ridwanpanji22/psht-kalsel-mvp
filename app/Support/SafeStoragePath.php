<?php

namespace App\Support;

/**
 * Canonical validator for relative storage paths that originate from a
 * FileUpload component.
 *
 * `str_starts_with($path, 'private/gallery-media/')` is not sufficient on its
 * own: a value such as `private/gallery-media/../event-covers/secret.jpg`
 * still satisfies the prefix while Flysystem normalizes the `..` segment and
 * resolves a file outside the intended directory. This helper rejects any
 * path that is not an unambiguous, traversal-free relative path, and requires
 * it to sit directly under one of the given prefixes.
 */
class SafeStoragePath
{
    public const EVENT_COVER_PREFIX = 'private/event-covers/';

    public const GALLERY_MEDIA_PREFIX = 'private/gallery-media/';

    /**
     * @param  list<string>  $allowedPrefixes
     */
    public static function isSafe(string $path, array $allowedPrefixes): bool
    {
        return self::normalize($path, $allowedPrefixes) !== null;
    }

    /**
     * Returns the normalized relative path when it is safe, otherwise null.
     *
     * @param  list<string>  $allowedPrefixes
     */
    public static function normalize(string $path, array $allowedPrefixes): ?string
    {
        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        // Backslashes are path separators on Windows and can bypass prefix
        // checks; reject them outright rather than translate.
        if (str_contains($path, '\\')) {
            return null;
        }

        // Only relative paths are allowed.
        if (str_starts_with($path, '/')) {
            return null;
        }

        // A drive-letter prefix (e.g. `C:/...`) is absolute on Windows.
        if (preg_match('#^[A-Za-z]:#', $path) === 1) {
            return null;
        }

        // Reject empty or dot segments and any traversal segment; require a
        // single leading separator (no `//` or leading/trailing separators).
        $segments = explode('/', $path);

        if ($segments === [] || in_array('', $segments, true)) {
            return null;
        }

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                return null;
            }
        }

        $normalized = implode('/', $segments);
        $matchesPrefix = false;

        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($normalized.'/', $prefix)) {
                $matchesPrefix = true;

                break;
            }
        }

        if (! $matchesPrefix) {
            return null;
        }

        return $normalized;
    }
}
