<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Gatovel\Storage\drivers\S3Driver;
use Gatovel\Storage\exceptions\StorageException;

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

$storage = new S3Driver(
    $client,
    'gatovel-storage-tests',
    'security-tests'
);

$passed = 0;

function rejected(callable $operation, string $name): void
{
    global $passed;

    try {
        $operation();
    } catch (StorageException) {
        echo "[OK] $name" . PHP_EOL;
        $passed++;
        return;
    }

    throw new RuntimeException("[FALHOU] $name");
}

echo "========== TESTES DE SEGURANCA S3 =========="
    . PHP_EOL;

rejected(
    fn() => $storage->put('../escape.txt', 'x'),
    'Bloqueia path traversal'
);

rejected(
    fn() => $storage->get('/etc/passwd'),
    'Bloqueia caminho absoluto'
);

rejected(
    fn() => $storage->delete('folder/../../file.txt'),
    'Bloqueia traversal em delete'
);

rejected(
    fn() => $storage->copy('../source', 'destination'),
    'Bloqueia origem insegura'
);

rejected(
    fn() => $storage->move('source', '../destination'),
    'Bloqueia destino inseguro'
);

rejected(
    fn() => $storage->put('', 'x'),
    'Bloqueia nome de arquivo vazio'
);

rejected(
    fn() => $storage->deleteDirectory(''),
    'Protege exclusao da raiz'
);

rejected(
    fn() => $storage->put("file\0.txt", 'x'),
    'Bloqueia byte nulo'
);

rejected(
    fn() => $storage->get('folder\\file.txt'),
    'Bloqueia separador inseguro'
);

echo "========== RESULTADO ==========" . PHP_EOL;
echo "$passed testes de seguranca passaram."
    . PHP_EOL;
