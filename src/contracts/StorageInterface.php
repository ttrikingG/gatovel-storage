<?php

declare(strict_types=1);

namespace Gatovel\Storage\contracts;

interface StorageInterface
{
    public function put(string $path, string $contents): void;

    public function get(string $path): string;

    public function exists(string $path): bool;

    public function delete(string $path): void;

    public function copy(string $source, string $destination): void;

    public function move(string $source, string $destination): void;

    /**
     * @return array<int, string>
     */
    public function files(string $directory = ''): array;

    public function makeDirectory(string $path): void;

    public function deleteDirectory(string $path): void;

    public function size(string $path): int;
}
