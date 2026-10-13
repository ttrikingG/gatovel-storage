<?php

declare(strict_types=1);

namespace Gatovel\Storage\drivers;

use Gatovel\Storage\contracts\StorageInterface;
use Gatovel\Storage\exceptions\StorageException;
use Gatovel\Storage\support\PathNormalizer;
use RuntimeException;
use FilesystemIterator;

final class LocalDriver implements StorageInterface
{
    private readonly string $root;

    public function __construct(string $root)
    {
        if (trim($root) === '') {
            throw StorageException::invalidPath($root);
        }

        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            throw StorageException::unableToCreateDirectory($root);
        }

        $resolved = realpath($root);

        if ($resolved === false || !is_dir($resolved)) {
            throw StorageException::invalidPath($root);
        }

        $this->root = $resolved;
    }

    public function put(string $path, string $contents): void
    {
        $fullPath = $this->resolvePath($path);

        $this->ensureParentDirectory($fullPath, $path);

        if (is_dir($fullPath)) {
            throw StorageException::unableToWrite($path);
        }

        if (@file_put_contents($fullPath, $contents, LOCK_EX) === false) {
            throw StorageException::unableToWrite($path);
        }
    }

    public function get(string $path): string
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw StorageException::fileNotFound($path);
        }

        $contents = @file_get_contents($fullPath);

        if ($contents === false) {
            throw StorageException::unableToRead($path);
        }

        return $contents;
    }

    public function exists(string $path): bool
    {
        return is_file($this->resolvePath($path));
    }

    public function size(string $path): int
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw StorageException::fileNotFound($path);
        }

        clearstatcache(true, $fullPath);

        $size = @filesize($fullPath);

        if ($size === false) {
            throw StorageException::unableToRead($path);
        }

        return $size;
    }

    public function delete(string $path): void
    {
        $fullPath = $this->resolvePath($path);

        if (!is_file($fullPath)) {
            throw StorageException::fileNotFound($path);
        }

        if (!@unlink($fullPath)) {
            throw StorageException::unableToDelete($path);
        }
    }

    public function copy(string $source, string $destination): void
    {
        $sourcePath = $this->resolvePath($source);
        $destinationPath = $this->resolvePath($destination);

        if (!is_file($sourcePath)) {
            throw StorageException::fileNotFound($source);
        }

        if ($sourcePath === $destinationPath) {
            throw StorageException::unableToCopy($source, $destination);
        }

        $this->ensureParentDirectory($destinationPath, $destination);

        if (is_dir($destinationPath)) {
            throw StorageException::unableToCopy($source, $destination);
        }

        if (!@copy($sourcePath, $destinationPath)) {
            throw StorageException::unableToCopy($source, $destination);
        }
    }

    public function move(string $source, string $destination): void
    {
        $sourcePath = $this->resolvePath($source);
        $destinationPath = $this->resolvePath($destination);

        if (!is_file($sourcePath)) {
            throw StorageException::fileNotFound($source);
        }

        if ($sourcePath === $destinationPath) {
            throw StorageException::unableToMove($source, $destination);
        }

        $this->ensureParentDirectory($destinationPath, $destination);

        if (is_dir($destinationPath)) {
            throw StorageException::unableToMove($source, $destination);
        }

        if (!@rename($sourcePath, $destinationPath)) {
            throw StorageException::unableToMove($source, $destination);
        }
    }

    public function files(string $directory = ''): array
    {
        $normalized = PathNormalizer::normalize($directory);

        $fullPath = $normalized === ''
            ? $this->root
            : $this->resolvePath($normalized);

        if (!is_dir($fullPath)) {
            if (file_exists($fullPath)) {
                throw StorageException::operationFailed(
                    'Storage path is not a directory: ' . $directory
                );
            }

            return [];
        }

        $files = [];

        try {
            $iterator = new FilesystemIterator(
                $fullPath,
                FilesystemIterator::SKIP_DOTS
            );

            foreach ($iterator as $item) {
                $name = $item->getFilename();

                $relativePath = $normalized === ''
                    ? $name
                    : $normalized . '/' . $name;

                // Do not follow or expose symbolic links.
                if ($item->isLink()) {
                    continue;
                }

                if ($item->isFile()) {
                    $files[] = $relativePath;
                }
            }
        } catch (\Throwable $exception) {
            throw StorageException::operationFailed(
                'Unable to list storage directory: ' . $directory,
                $exception
            );
        }

        sort($files, SORT_STRING);

        return $files;
    }

    public function makeDirectory(string $path): void
    {
        $fullPath = $this->resolvePath($path);

        if (is_dir($fullPath)) {
            return;
        }

        if (file_exists($fullPath)) {
            throw StorageException::unableToCreateDirectory($path);
        }

        if (!@mkdir($fullPath, 0775, true) && !is_dir($fullPath)) {
            throw StorageException::unableToCreateDirectory($path);
        }

        // Revalidate after directory creation.
        $this->resolvePath($path);
    }

    public function deleteDirectory(string $path): void
    {
        $normalized = PathNormalizer::normalize($path);

        if ($normalized === '') {
            throw StorageException::invalidPath($path);
        }

        $fullPath = $this->resolvePath($normalized);

        if (!is_dir($fullPath)) {
            throw StorageException::unableToDeleteDirectory($path);
        }

        $this->removeDirectoryContents($fullPath);

        if (!@rmdir($fullPath)) {
            throw StorageException::unableToDeleteDirectory($path);
        }
    }

    private function removeDirectoryContents(string $directory): void
    {
        try {
            $iterator = new FilesystemIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            );

            foreach ($iterator as $item) {
                $path = $item->getPathname();

                if ($item->isLink()) {
                    // Unlink the symlink itself, never its target.
                    if (!@unlink($path)) {
                        throw StorageException::unableToDelete($path);
                    }

                    continue;
                }

                if ($item->isDir()) {
                    $this->removeDirectoryContents($path);

                    if (!@rmdir($path)) {
                        throw StorageException::unableToDeleteDirectory($path);
                    }

                    continue;
                }

                if (!@unlink($path)) {
                    throw StorageException::unableToDelete($path);
                }
            }
        } catch (StorageException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw StorageException::operationFailed(
                'Unable to remove directory contents: ' . $directory,
                $exception
            );
        }
    }

    private function ensureParentDirectory(
        string $fullPath,
        string $originalPath
    ): void {
        $directory = dirname($fullPath);

        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw StorageException::unableToCreateDirectory($directory);
            }
        }

        $this->resolvePath($originalPath);
    }

    private function resolvePath(string $path): string
    {
        $normalized = PathNormalizer::normalize($path);

        if ($normalized === '') {
            throw StorageException::invalidPath($path);
        }

        $current = $this->root;

        foreach (explode('/', $normalized) as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;

            if (is_link($current)) {
                throw StorageException::invalidPath($path);
            }
        }

        return $current;
    }
}
