<?php

declare(strict_types=1);

namespace Gatovel\Storage;

use Gatovel\Storage\contracts\StorageInterface;
use Gatovel\Storage\exceptions\StorageException;

final class StorageManager
{
    /**
     * @var array<string, StorageInterface>
     */
    private array $drivers = [];

    private ?string $defaultDriver = null;

    public function addDriver(
        string $name,
        StorageInterface $driver
    ): self {
        $this->validateName($name);

        if (isset($this->drivers[$name])) {
            throw StorageException::operationFailed(
                sprintf('Storage driver already registered: %s', $name)
            );
        }

        $this->drivers[$name] = $driver;

        if ($this->defaultDriver === null) {
            $this->defaultDriver = $name;
        }

        return $this;
    }

    public function disk(?string $name = null): StorageInterface
    {
        $name ??= $this->defaultDriver;

        if ($name === null || !isset($this->drivers[$name])) {
            throw StorageException::operationFailed(
                sprintf(
                    'Storage driver not found: %s',
                    $name ?? '(default)'
                )
            );
        }

        return $this->drivers[$name];
    }

    public function setDefaultDriver(string $name): self
    {
        if (!isset($this->drivers[$name])) {
            throw StorageException::operationFailed(
                sprintf('Storage driver not found: %s', $name)
            );
        }

        $this->defaultDriver = $name;

        return $this;
    }

    public function defaultDriver(): ?string
    {
        return $this->defaultDriver;
    }

    public function hasDriver(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /**
     * @return array<int, string>
     */
    public function drivers(): array
    {
        return array_keys($this->drivers);
    }

    private function validateName(string $name): void
    {
        if (
            $name === '' ||
            preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name) !== 1
        ) {
            throw StorageException::operationFailed(
                sprintf('Invalid storage driver name: %s', $name)
            );
        }
    }
}
