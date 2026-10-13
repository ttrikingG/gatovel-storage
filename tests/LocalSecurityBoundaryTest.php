<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Gatovel\Storage\drivers\LocalDriver;
use Gatovel\Storage\exceptions\StorageException;

function verifyBoundary(
    callable $operation,
    string $description
): void {
    try {
        $operation();
    } catch (StorageException $exception) {
        echo "[OK] $description" . PHP_EOL;
        return;
    }

    throw new RuntimeException(
        "[FALHOU] $description"
    );
}

$base = sys_get_temp_dir()
    . '/gatovel-security-' . bin2hex(random_bytes(8));

$root = $base . '/storage';
$outside = $base . '/outside';

mkdir($root, 0700, true);
mkdir($outside, 0700, true);

file_put_contents($outside . '/secret.txt', 'PRIVATE');

$driver = new LocalDriver($root);

echo "========== SEGURANCA LOCAL ==========" . PHP_EOL;

try {
    if (!symlink($outside, $root . '/escape')) {
        throw new RuntimeException('Unable to create symlink');
    }

    verifyBoundary(
        fn() => $driver->get('escape/secret.txt'),
        'Leitura externa bloqueada'
    );

    verifyBoundary(
        fn() => $driver->put('escape/new.txt', 'TEST'),
        'Escrita externa bloqueada'
    );

    verifyBoundary(
        fn() => $driver->delete('escape/secret.txt'),
        'Exclusao externa bloqueada'
    );

    verifyBoundary(
        fn() => $driver->copy(
            'escape/secret.txt',
            'copy.txt'
        ),
        'Copia externa bloqueada'
    );

    verifyBoundary(
        fn() => $driver->move(
            'escape/secret.txt',
            'moved.txt'
        ),
        'Movimentacao externa bloqueada'
    );

    verifyBoundary(
        fn() => $driver->files('escape'),
        'Listagem externa bloqueada'
    );

    if (file_get_contents($outside . '/secret.txt') !== 'PRIVATE') {
        throw new RuntimeException(
            'External file was modified'
        );
    }

    if (file_exists($outside . '/new.txt')) {
        throw new RuntimeException(
            'External file was created'
        );
    }

    echo "[OK] Arquivos externos preservados" . PHP_EOL;
    echo "========== 7 TESTES APROVADOS =========="
        . PHP_EOL;
} finally {
    if (is_link($root . '/escape')) {
        unlink($root . '/escape');
    }

    if (is_file($outside . '/secret.txt')) {
        unlink($outside . '/secret.txt');
    }

    rmdir($root);
    rmdir($outside);
    rmdir($base);

    echo "[OK] Limpeza concluida" . PHP_EOL;
}
