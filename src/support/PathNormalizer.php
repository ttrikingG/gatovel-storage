<?php

declare(strict_types=1);

namespace Gatovel\Storage\support;

use Gatovel\Storage\exceptions\StorageException;

final class PathNormalizer
{
    public static function normalize(string $path): string
    {
        if (
            str_contains($path, "\0") ||
            str_contains($path, '\\') ||
            str_starts_with($path, '/') ||
            preg_match('/^[a-zA-Z]:/', $path) === 1
        ) {
            throw StorageException::invalidPath($path);
        }

        $segments = explode('/', $path);
        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw StorageException::invalidPath($path);
            }

            if (preg_match('/[\x00-\x1F\x7F]/', $segment) === 1) {
                throw StorageException::invalidPath($path);
            }

            $normalized[] = $segment;
        }

        return implode('/', $normalized);
    }
}
