<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Gatovel\Storage\drivers\S3Driver;
use Gatovel\Storage\exceptions\StorageException;

echo "========== TESTE DE FALHA PARCIAL S3 =========="
    . PHP_EOL;

$mock = new MockHandler();

$mock->append(new Result([
    'Contents' => [
        ['Key' => 'tests/documents/file.txt'],
    ],
    'IsTruncated' => false,
]));

$mock->append(new Result([
    'Errors' => [
        [
            'Key' => 'tests/documents/file.txt',
            'Code' => 'AccessDenied',
            'Message' => 'Access denied',
        ],
    ],
]));

$client = new S3Client([
    'version' => 'latest',
    'region' => 'us-east-1',
    'credentials' => [
        'key' => 'test',
        'secret' => 'test',
    ],
    'handler' => $mock,
]);

$storage = new S3Driver(
    $client,
    'gatovel-storage-tests',
    'tests'
);

try {
    $storage->deleteDirectory('documents');

    throw new RuntimeException(
        '[FALHOU] O driver ignorou a falha parcial.'
    );
} catch (StorageException $exception) {
    echo '[OK] StorageException lancada.' . PHP_EOL;

    if ($exception->getPrevious() === null) {
        throw new RuntimeException(
            '[FALHOU] Causa original nao preservada.'
        );
    }

    echo '[OK] Causa original preservada.' . PHP_EOL;
}

echo "========== TESTE DE FALHA PARCIAL APROVADO =========="
    . PHP_EOL;
