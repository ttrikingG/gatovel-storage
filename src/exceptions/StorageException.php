<?php

declare(strict_types=1);

namespace Gatovel\Storage\exceptions;

use RuntimeException;
use Throwable;

class StorageException extends RuntimeException
{
    public static function fileNotFound(string $path): self
    {
        return new self(
            sprintf('Storage file not found: %s', $path)
        );
    }

    public static function unableToWrite(string $path): self
    {
        return new self(
            sprintf('Unable to write storage file: %s', $path)
        );
    }

    public static function unableToRead(string $path): self
    {
        return new self(
            sprintf('Unable to read storage file: %s', $path)
        );
    }

    public static function unableToDelete(string $path): self
    {
        return new self(
            sprintf('Unable to delete storage file: %s', $path)
        );
    }

    public static function unableToCopy(
        string $source,
        string $destination
    ): self {
        return new self(
            sprintf(
                'Unable to copy storage file from "%s" to "%s".',
                $source,
                $destination
            )
        );
    }

    public static function unableToMove(
        string $source,
        string $destination
    ): self {
        return new self(
            sprintf(
                'Unable to move storage file from "%s" to "%s".',
                $source,
                $destination
            )
        );
    }

    public static function unableToCreateDirectory(string $path): self
    {
        return new self(
            sprintf('Unable to create storage directory: %s', $path)
        );
    }

    public static function unableToDeleteDirectory(string $path): self
    {
        return new self(
            sprintf('Unable to delete storage directory: %s', $path)
        );
    }

    public static function invalidPath(string $path): self
    {
        return new self(
            sprintf('Invalid storage path: %s', $path)
        );
    }

    public static function operationFailed(
        string $message,
        ?Throwable $previous = null
    ): self {
        return new self(
            $message,
            0,
            $previous
        );
    }
}
