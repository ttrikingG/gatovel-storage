<?php

declare(strict_types=1);

namespace Gatovel\Storage\drivers;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Gatovel\Storage\contracts\StorageInterface;
use Gatovel\Storage\exceptions\StorageException;
use Gatovel\Storage\support\PathNormalizer;
use Throwable;

final class S3Driver implements StorageInterface
{
    public function __construct(
        private readonly S3Client $client,
        private readonly string $bucket,
        private readonly string $prefix = ''
    ) {
        if (trim($bucket) === '') {
            throw StorageException::operationFailed(
                'S3 bucket name cannot be empty.'
            );
        }

        PathNormalizer::normalize($prefix);
    }

    public function put(string $path, string $contents): void
    {
        $key = $this->key($path);

        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $contents,
            ]);
        } catch (Throwable $exception) {
            throw StorageException::unableToWrite($path);
        }
    }

    public function get(string $path): string
    {
        $key = $this->key($path);

        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return (string) $result['Body'];
        } catch (AwsException $exception) {
            if ($this->isNotFound($exception)) {
                throw StorageException::fileNotFound($path);
            }

            throw StorageException::unableToRead($path);
        } catch (Throwable $exception) {
            throw StorageException::unableToRead($path);
        }
    }

    public function exists(string $path): bool
    {
        $key = $this->key($path);

        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return true;
        } catch (AwsException $exception) {
            if ($this->isNotFound($exception)) {
                return false;
            }

            throw StorageException::operationFailed(
                'Unable to check S3 object existence.',
                $exception
            );
        } catch (Throwable $exception) {
            throw StorageException::operationFailed(
                'Unexpected error checking S3 object existence.',
                $exception
            );
        }
    }

    public function delete(string $path): void
    {
        $key = $this->key($path);

        if (!$this->exists($path)) {
            throw StorageException::fileNotFound($path);
        }

        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
        } catch (Throwable $exception) {
            throw StorageException::unableToDelete($path);
        }
    }

    public function copy(string $source, string $destination): void
    {
        $sourceKey = $this->key($source);
        $destinationKey = $this->key($destination);

        if ($sourceKey === $destinationKey) {
            throw StorageException::unableToCopy(
                $source,
                $destination
            );
        }

        if (!$this->exists($source)) {
            throw StorageException::fileNotFound($source);
        }

        try {
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $destinationKey,
                'CopySource' => $this->copySource($sourceKey),
            ]);
        } catch (Throwable $exception) {
            throw StorageException::unableToCopy($source, $destination);
        }
    }

    public function move(string $source, string $destination): void
    {
        $sourceKey = $this->key($source);
        $destinationKey = $this->key($destination);

        if ($sourceKey === $destinationKey) {
            if (!$this->exists($source)) {
                throw StorageException::fileNotFound($source);
            }

            throw StorageException::unableToMove(
                $source,
                $destination
            );
        }

        try {
            $this->copy($source, $destination);
            $this->delete($source);
        } catch (Throwable $exception) {
            throw StorageException::operationFailed(
                sprintf(
                    'Unable to move storage file from "%s" to "%s".',
                    $source,
                    $destination
                ),
                $exception
            );
        }
    }

    public function files(string $directory = ''): array
    {
        $normalized = PathNormalizer::normalize($directory);

        if ($normalized !== '' && $this->exists($normalized)) {
            throw StorageException::operationFailed(
                'Storage path is not a directory: ' . $directory
            );
        }

        $prefix = $normalized === ''
            ? $this->basePrefix()
            : $this->basePrefix() . $normalized . '/';

        $files = [];
        $token = null;

        try {
            do {
                $parameters = [
                    'Bucket' => $this->bucket,
                    'Prefix' => $prefix,
                    'Delimiter' => '/',
                ];

                if ($token !== null) {
                    $parameters['ContinuationToken'] = $token;
                }

                $result = $this->client->listObjectsV2($parameters);

                foreach ($result['Contents'] ?? [] as $object) {
                    $key = (string) $object['Key'];

                    if (str_ends_with($key, '/')) {
                        continue;
                    }

                    $files[] = substr(
                        $key,
                        strlen($this->basePrefix())
                    );
                }

                $token = $result['NextContinuationToken'] ?? null;
            } while ($token !== null);

            sort($files);

            return $files;
        } catch (Throwable $exception) {
            throw StorageException::operationFailed(
                'Unable to list S3 objects.'
            );
        }
    }

    public function makeDirectory(string $path): void
    {
        $key = $this->key($path);

        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key . '/',
                'Body' => '',
            ]);
        } catch (Throwable $exception) {
            throw StorageException::unableToCreateDirectory($path);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $normalized = PathNormalizer::normalize($path);

        if ($normalized === '') {
            throw StorageException::invalidPath($path);
        }

        $prefix = $this->basePrefix() . $normalized . '/';

        try {
            while (true) {
                $result = $this->client->listObjectsV2([
                    'Bucket' => $this->bucket,
                    'Prefix' => $prefix,
                    'MaxKeys' => 1000,
                ]);

                $objects = [];

                foreach ($result['Contents'] ?? [] as $object) {
                    $objects[] = [
                        'Key' => (string) $object['Key'],
                    ];
                }

                if ($objects === []) {
                    return;
                }

                $deleted = $this->client->deleteObjects([
                    'Bucket' => $this->bucket,
                    'Delete' => [
                        'Objects' => $objects,
                        'Quiet' => false,
                    ],
                ]);

                if (!empty($deleted['Errors'])) {
                    throw StorageException::operationFailed(
                        'S3 reported errors while deleting objects.'
                    );
                }

                $remaining = $this->client->listObjectsV2([
                    'Bucket' => $this->bucket,
                    'Prefix' => $prefix,
                    'MaxKeys' => 1,
                ]);

                if (empty($remaining['Contents'])) {
                    return;
                }

                $firstKey = (string) $remaining['Contents'][0]['Key'];

                foreach ($objects as $object) {
                    if ($object['Key'] === $firstKey) {
                        throw StorageException::operationFailed(
                            'S3 deletion did not remove an expected object.'
                        );
                    }
                }
            }
        } catch (Throwable $exception) {
            throw StorageException::operationFailed(
                'Unable to delete storage directory: ' . $path,
                $exception
            );
        }
    }

    public function size(string $path): int
    {
        $key = $this->key($path);

        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return (int) $result['ContentLength'];
        } catch (AwsException $exception) {
            if ($this->isNotFound($exception)) {
                throw StorageException::fileNotFound($path);
            }

            throw StorageException::unableToRead($path);
        } catch (Throwable $exception) {
            throw StorageException::unableToRead($path);
        }
    }

    private function key(string $path): string
    {
        $normalized = PathNormalizer::normalize($path);

        if ($normalized === '') {
            throw StorageException::invalidPath($path);
        }

        return $this->basePrefix() . $normalized;
    }

    private function basePrefix(): string
    {
        $prefix = PathNormalizer::normalize($this->prefix);

        return $prefix === '' ? '' : $prefix . '/';
    }

    private function copySource(string $key): string
    {
        return $this->bucket . '/' . implode(
            '/',
            array_map(
                'rawurlencode',
                explode('/', $key)
            )
        );
    }

    private function isNotFound(AwsException $exception): bool
    {
        return in_array(
            $exception->getStatusCode(),
            [404],
            true
        ) || in_array(
            $exception->getAwsErrorCode(),
            ['NoSuchKey', 'NotFound'],
            true
        );
    }
}
