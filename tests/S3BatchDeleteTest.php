<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Gatovel\Storage\drivers\S3Driver;

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
$prefix = 'batch-tests/' . bin2hex(random_bytes(8));
$directory = 'large-directory';
$fullPrefix = $prefix . '/' . $directory . '/';

$storage = new S3Driver($client, $bucket, $prefix);

echo "========== TESTE DE EXCLUSAO EM LOTES ==========" . PHP_EOL;
echo "Prefixo: $fullPrefix" . PHP_EOL;

try {
    echo "========== CRIANDO 1005 OBJETOS ==========" . PHP_EOL;

    for ($start = 1; $start <= 1005; $start += 100) {
        $promises = [];

        for ($i = $start; $i < min($start + 100, 1006); $i++) {
            $key = $fullPrefix . sprintf('file-%04d.txt', $i);

            $promises[] = $client->putObjectAsync([
                'Bucket' => $bucket,
                'Key' => $key,
                'Body' => 'Gatovel',
            ]);
        }

        \GuzzleHttp\Promise\Utils::unwrap($promises);
    }

    echo "[OK] 1005 objetos criados." . PHP_EOL;

    echo "========== EXCLUINDO COM S3DRIVER ==========" . PHP_EOL;

    $storage->deleteDirectory($directory);

    echo "[OK] deleteDirectory() executado." . PHP_EOL;

    echo "========== CONFERINDO RESULTADO ==========" . PHP_EOL;

    $result = $client->listObjectsV2([
        'Bucket' => $bucket,
        'Prefix' => $fullPrefix,
        'MaxKeys' => 1,
    ]);

    if (!empty($result['Contents'])) {
        throw new RuntimeException(
            'Ainda existem objetos no diretorio.'
        );
    }

    echo "[OK] Nenhum objeto restante." . PHP_EOL;
    echo "========== TESTE DE LOTES APROVADO ==========" . PHP_EOL;
} finally {
    echo "========== LIMPEZA DE SEGURANCA ==========" . PHP_EOL;

    do {
        $result = $client->listObjectsV2([
            'Bucket' => $bucket,
            'Prefix' => $prefix . '/',
            'MaxKeys' => 1000,
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
            throw new RuntimeException(
                'Falha na limpeza dos objetos de teste.'
            );
        }
    } while (true);

    echo "[OK] Limpeza concluida." . PHP_EOL;
}
