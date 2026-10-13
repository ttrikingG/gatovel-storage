<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Gatovel\Storage\drivers\LocalDriver;
use Gatovel\Storage\exceptions\StorageException;

function assertStorage(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }

    echo "[OK] {$message}" . PHP_EOL;
}

function expectStorageException(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (StorageException $exception) {
        echo "[OK] {$message}" . PHP_EOL;
        return;
    }

    throw new RuntimeException(
        "Falha: excecao esperada nao ocorreu - {$message}"
    );
}

$root = sys_get_temp_dir()
    . '/gatovel-storage-security-'
    . bin2hex(random_bytes(6));

$outside = sys_get_temp_dir()
    . '/gatovel-storage-outside-'
    . bin2hex(random_bytes(6));

$storage = new LocalDriver($root);

try {
    echo "========== TESTES DE SEGURANCA ==========" . PHP_EOL;

    expectStorageException(
        fn () => $storage->put('../outside.txt', 'invasao'),
        'Path traversal bloqueado'
    );

    expectStorageException(
        fn () => $storage->get('/etc/passwd'),
        'Caminho absoluto bloqueado'
    );

    expectStorageException(
        fn () => $storage->get('inexistente.txt'),
        'Leitura de arquivo inexistente tratada'
    );

    expectStorageException(
        fn () => $storage->delete('inexistente.txt'),
        'Exclusao de arquivo inexistente tratada'
    );

    expectStorageException(
        fn () => $storage->copy(
            'inexistente.txt',
            'destino.txt'
        ),
        'Copia de arquivo inexistente tratada'
    );

    echo "========== TESTES DE SOBRESCRITA ==========" . PHP_EOL;

    $storage->put('products/item.txt', 'primeira versao');
    $storage->put('products/item.txt', 'segunda versao');

    assertStorage(
        $storage->get('products/item.txt') === 'segunda versao',
        'put() sobrescreve arquivo existente'
    );

    $storage->put('products/origem.txt', 'conteudo copiado');
    $storage->copy('products/origem.txt', 'products/item.txt');

    assertStorage(
        $storage->get('products/item.txt') === 'conteudo copiado',
        'copy() sobrescreve destino existente'
    );

    $storage->put('products/novo.txt', 'conteudo movido');
    $storage->move('products/novo.txt', 'products/item.txt');

    assertStorage(
        $storage->get('products/item.txt') === 'conteudo movido',
        'move() substitui destino existente'
    );

    assertStorage(
        !$storage->exists('products/novo.txt'),
        'move() remove arquivo de origem'
    );

    echo "========== TESTES DE DIRETORIOS ==========" . PHP_EOL;

    $storage->makeDirectory('empty');

    assertStorage(
        $storage->files('empty') === [],
        'Listagem de diretorio vazio'
    );

    echo "========== TESTES DE SYMLINK ==========" . PHP_EOL;

    if (!mkdir($outside, 0700, true)) {
        throw new RuntimeException('Falha ao criar diretorio externo.');
    }

    file_put_contents($outside . '/secret.txt', 'SEGREDO');

    if (!symlink($outside, $root . '/external-link')) {
        throw new RuntimeException('Falha ao criar symlink de teste.');
    }

    expectStorageException(
        fn () => $storage->get('external-link/secret.txt'),
        'Leitura atraves de symlink bloqueada'
    );

    expectStorageException(
        fn () => $storage->put('external-link/hack.txt', 'invasao'),
        'Escrita atraves de symlink bloqueada'
    );

    assertStorage(
        !file_exists($outside . '/hack.txt'),
        'Nenhum arquivo criado fora da raiz'
    );

    echo "========== RESULTADO FINAL ==========" . PHP_EOL;
    echo "TODOS OS TESTES DA ETAPA 9 PASSARAM!" . PHP_EOL;
} finally {
    // Cleanup is deliberately limited to known test paths.
    if (is_link($root . '/external-link')) {
        unlink($root . '/external-link');
    }

    foreach ([
        'products/item.txt',
        'products/origem.txt',
        'products/novo.txt'
    ] as $file) {
        $fullPath = $root . '/' . $file;

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    foreach ([
        $root . '/products',
        $root . '/empty',
        $root
    ] as $directory) {
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }

    if (is_file($outside . '/secret.txt')) {
        unlink($outside . '/secret.txt');
    }

    if (is_dir($outside)) {
        rmdir($outside);
    }
}
