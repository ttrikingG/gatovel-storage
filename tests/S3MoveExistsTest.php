<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Aws\MockHandler;
use Aws\S3\S3Client;
use Gatovel\Storage\drivers\S3Driver;
use Gatovel\Storage\exceptions\StorageException;

function verify(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("[FALHOU] $message");
    }

    echo "[OK] $message" . PHP_EOL;
}

$config = [
    'version' => 'latest',
    'region' => 'us-east-1',
    'endpoint' => 'http://host.docker.internal:4566',
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key' => 'test',
        'secret' => 'test',
    ],
];

$client = new S3Client($config);
$bucket = 'gatovel-storage-tests';
$prefix = 'regression/' . bin2hex(random_bytes(8));
$storage = new S3Driver($client, $bucket, $prefix);

echo "========== TESTES DE REGRESSAO S3 ==========" . PHP_EOL;

try {
    $storage->put('same.txt', 'Gatovel');

    $copyRejected = false;

    try {
        $storage->copy('same.txt', 'same.txt');
    } catch (StorageException $exception) {
        $copyRejected = true;
    }

    verify($copyRejected, 'copy rejeita origem igual ao destino');

    $moveRejected = false;

    try {
        $storage->move('same.txt', 'same.txt');
    } catch (StorageException $exception) {
        $moveRejected = true;
    }

    verify($moveRejected, 'move rejeita origem igual ao destino');

    verify(
        $storage->get('same.txt') === 'Gatovel',
        'arquivo original permanece intacto'
    );

    $missingRejected = false;

    try {
        $storage->move('missing.txt', 'missing.txt');
    } catch (StorageException $exception) {
        $missingRejected = str_contains(
            $exception->getMessage(),
            'not found'
        );
    }

    verify($missingRejected, 'move rejeita arquivo inexistente');

    verify(
        !$storage->exists('missing.txt'),
        'exists retorna false para arquivo inexistente'
    );

    $mock = new MockHandler();

    $mock->append(
        new RuntimeException('Simulated connection failure')
    );

    $mockClient = new S3Client([
        ...$config,
        'handler' => $mock,
        'retries' => 0,
    ]);

    $mockStorage = new S3Driver(
        $mockClient,
        $bucket,
        $prefix
    );

    $wrapped = false;

    try {
        $mockStorage->exists('unavailable.txt');
    } catch (StorageException $exception) {
        $wrapped = $exception->getPrevious() !== null;
    }

    verify($wrapped, 'exists preserva causa da falha');

    echo "========== 6 TESTES APROVADOS =========="
        . PHP_EOL;
} finally {
    $client->deleteObject([
        'Bucket' => $bucket,
        'Key' => $prefix . '/same.txt',
    ]);

    echo "[OK] Limpeza concluida." . PHP_EOL;
}
