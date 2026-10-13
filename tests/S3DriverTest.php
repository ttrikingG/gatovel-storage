<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Gatovel\Storage\drivers\S3Driver;
use Gatovel\Storage\exceptions\StorageException;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("[FALHOU] $message");
    }

    echo "[OK] $message" . PHP_EOL;
}

$client = new S3Client([
    'version' => 'latest',
    'region' => 'us-east-1',
    'endpoint' => 'http://host.docker.internal:4566',
    'use_path_style_endpoint' => true,
    'credentials' => ['key' => 'test', 'secret' => 'test'],
]);

$bucket = 'gatovel-storage-tests';
$prefix = 'integration/' . bin2hex(random_bytes(8));
$storage = new S3Driver($client, $bucket, $prefix);

echo "========== TESTES S3DRIVER ==========" . PHP_EOL;
echo "Prefixo: $prefix" . PHP_EOL;

try {
    $storage->put('docs/test.txt', 'Gatovel Storage');

    check($storage->exists('docs/test.txt'), 'put e exists');
    check($storage->get('docs/test.txt') === 'Gatovel Storage', 'get');
    check($storage->size('docs/test.txt') === 15, 'size');

    $storage->copy('docs/test.txt', 'docs/copy.txt');
    check($storage->exists('docs/copy.txt'), 'copy');

    $storage->move('docs/copy.txt', 'docs/moved.txt');
    check(
        $storage->exists('docs/moved.txt')
        && !$storage->exists('docs/copy.txt'),
        'move'
    );

    $files = $storage->files('docs');
    check(
        in_array('docs/test.txt', $files, true)
        && in_array('docs/moved.txt', $files, true),
        'files'
    );

    $storage->makeDirectory('empty');
    check($storage->files('empty') === [], 'makeDirectory');

    $storage->delete('docs/test.txt');
    check(!$storage->exists('docs/test.txt'), 'delete');

    $storage->deleteDirectory('docs');
    check(!$storage->exists('docs/moved.txt'), 'deleteDirectory');

    $storage->deleteDirectory('empty');
    check($storage->files('empty') === [], 'deleteDirectory vazio');

    $notFound = false;

    try {
        $storage->get('missing.txt');
    } catch (StorageException) {
        $notFound = true;
    }

    check($notFound, 'arquivo inexistente');

    echo "========== 11 TESTES PASSARAM ==========" . PHP_EOL;
} finally {
    echo "========== LIMPEZA ==========" . PHP_EOL;

    do {
        $result = $client->listObjectsV2([
            'Bucket' => $bucket,
            'Prefix' => $prefix . '/',
        ]);

        $objects = [];

        foreach ($result['Contents'] ?? [] as $object) {
            $objects[] = ['Key' => (string) $object['Key']];
        }

        if ($objects === []) {
            break;
        }

        $deleted = $client->deleteObjects([
            'Bucket' => $bucket,
            'Delete' => [
                'Objects' => $objects,
                'Quiet' => false,
            ],
        ]);

        if (!empty($deleted['Errors'])) {
            throw new RuntimeException('Falha na limpeza S3.');
        }
    } while (true);

    echo "[OK] Limpeza concluida." . PHP_EOL;
}
