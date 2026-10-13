<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Gatovel\Storage\StorageManager;
use Gatovel\Storage\drivers\LocalDriver;
use Gatovel\Storage\exceptions\StorageException;

function assertManager(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }

    echo "[OK] {$message}" . PHP_EOL;
}

function expectManagerException(
    callable $operation,
    string $message
): void {
    try {
        $operation();
    } catch (StorageException $exception) {
        echo "[OK] {$message}" . PHP_EOL;
        return;
    }

    throw new RuntimeException(
        "Excecao esperada nao ocorreu: {$message}"
    );
}

$base = sys_get_temp_dir()
    . '/gatovel-storage-manager-'
    . bin2hex(random_bytes(6));

$manager = new StorageManager();

try {
    echo "========== MANAGER SEM DRIVERS ==========" . PHP_EOL;

    assertManager(
        $manager->defaultDriver() === null,
        'Nenhum driver padrao inicialmente'
    );

    assertManager(
        $manager->drivers() === [],
        'Lista inicial de drivers vazia'
    );

    expectManagerException(
        fn () => $manager->disk(),
        'Acesso sem driver registrado bloqueado'
    );

    echo "========== REGISTRO DE DRIVERS ==========" . PHP_EOL;

    $local = new LocalDriver($base . '/local');
    $backup = new LocalDriver($base . '/backup');

    assertManager(
        $manager->addDriver('local', $local) === $manager,
        'Registro permite encadeamento'
    );

    $manager->addDriver('backup', $backup);

    assertManager(
        $manager->defaultDriver() === 'local',
        'Primeiro driver definido como padrao'
    );

    assertManager(
        $manager->hasDriver('local'),
        'Driver local registrado'
    );

    assertManager(
        $manager->hasDriver('backup'),
        'Driver backup registrado'
    );

    assertManager(
        !$manager->hasDriver('s3'),
        'Driver inexistente identificado'
    );

    assertManager(
        $manager->drivers() === ['local', 'backup'],
        'Listagem de drivers correta'
    );

    echo "========== ISOLAMENTO DOS DRIVERS ==========" . PHP_EOL;

    $manager->disk('local')->put('test.txt', 'LOCAL');
    $manager->disk('backup')->put('test.txt', 'BACKUP');

    assertManager(
        $manager->disk('local')->get('test.txt') === 'LOCAL',
        'Conteudo do driver local preservado'
    );

    assertManager(
        $manager->disk('backup')->get('test.txt') === 'BACKUP',
        'Conteudo do driver backup preservado'
    );

    echo "========== DRIVER PADRAO ==========" . PHP_EOL;

    assertManager(
        $manager->disk() === $local,
        'Disco padrao inicialmente local'
    );

    assertManager(
        $manager->setDefaultDriver('backup') === $manager,
        'Alteracao do padrao permite encadeamento'
    );

    assertManager(
        $manager->disk() === $backup,
        'Novo disco padrao selecionado'
    );

    echo "========== VALIDACAO DE ERROS ==========" . PHP_EOL;

    expectManagerException(
        fn () => $manager->addDriver('local', $local),
        'Registro duplicado bloqueado'
    );

    expectManagerException(
        fn () => $manager->addDriver('', $local),
        'Nome vazio bloqueado'
    );

    expectManagerException(
        fn () => $manager->addDriver('../outside', $local),
        'Nome com caminho invalido bloqueado'
    );

    expectManagerException(
        fn () => $manager->addDriver('123driver', $local),
        'Nome iniciado por numero bloqueado'
    );

    expectManagerException(
        fn () => $manager->disk('inexistente'),
        'Consulta de driver inexistente bloqueada'
    );

    expectManagerException(
        fn () => $manager->setDefaultDriver('inexistente'),
        'Driver padrao inexistente bloqueado'
    );

    assertManager(
        $manager->defaultDriver() === 'backup',
        'Driver padrao preservado apos erros'
    );

    echo "========== RESULTADO FINAL ==========" . PHP_EOL;
    echo "TODOS OS TESTES DO STORAGE MANAGER PASSARAM!" . PHP_EOL;
} finally {
    foreach (['local', 'backup'] as $disk) {
        $file = $base . '/' . $disk . '/test.txt';

        if (is_file($file)) {
            unlink($file);
        }

        $directory = $base . '/' . $disk;

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }

    if (is_dir($base)) {
        rmdir($base);
    }
}
