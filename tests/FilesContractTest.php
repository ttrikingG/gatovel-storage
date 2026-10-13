<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Gatovel\Storage\contracts\StorageInterface;
use Gatovel\Storage\drivers\LocalDriver;
use Gatovel\Storage\drivers\S3Driver;
use Gatovel\Storage\exceptions\StorageException;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("[FALHOU] $message");
    }

    echo "[OK] $message" . PHP_EOL;
}

function testFiles(StorageInterface $disk, string $name): void
{
    echo "========== $name ==========" . PHP_EOL;

    check(
        $disk->files('missing') === [],
        'Diretorio inexistente retorna array vazio'
    );

    $disk->makeDirectory('empty');

    check(
        $disk->files('empty') === [],
        'Diretorio vazio retorna array vazio'
    );

    $disk->put('documents/example.txt', 'Gatovel');

    check(
        $disk->files('documents') === ['documents/example.txt'],
        'Listagem retorna caminho relativo'
    );

    $disk->put('regular.txt', 'Gatovel');

    $rejected = false;

    try {
        $disk->files('regular.txt');
    } catch (StorageException $exception) {
        $rejected = true;
    }

    check(
        $rejected,
        'Arquivo comum nao e tratado como diretorio'
    );
}

$root = sys_get_temp_dir()
    . '/gatovel-files-' . bin2hex(random_bytes(8));

$prefix = 'contract/' . bin2hex(random_bytes(8));

$client = new S3Client([
    'version' => 'latest',
    'region' => 'us-east-1',
    'endpoint' => 'http://host.docker.internal:4566',
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key' => 'test',
        'secret' => 'test',
    ],
]);

$bucket = 'gatovel-storage-tests';

$local = new LocalDriver($root);
$s3 = new S3Driver($client, $bucket, $prefix);

try {
    testFiles($local, 'LOCAL DRIVER');
    testFiles($s3, 'S3 DRIVER');

    echo "========== 8 TESTES APROVADOS =========="
        . PHP_EOL;
} finally {
    $local->deleteDirectory('documents');
    $local->deleteDirectory('empty');
    $local->delete('regular.txt');

    if (is_dir($root)) {
        rmdir($root);
    }

    $s3->deleteDirectory('documents');
    $s3->deleteDirectory('empty');
    $s3->delete('regular.txt');

    echo "[OK] Limpeza concluida." . PHP_EOL;
}
