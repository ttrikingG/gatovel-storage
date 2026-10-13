
# Gatovel Storage

Biblioteca modular e independente para gerenciamento de arquivos em aplicações PHP.

O **Gatovel Storage** fornece uma interface padronizada para operações de armazenamento, permitindo trabalhar com diferentes drivers sem acoplar a aplicação a um sistema de arquivos específico.

O pacote faz parte do ecossistema Gatovel, mas pode ser utilizado em qualquer aplicação PHP compatível.

## Funcionalidades

- Arquitetura modular baseada em contratos.
- Armazenamento local.
- Integração opcional com Amazon S3.
- Criação, leitura e exclusão de arquivos.
- Cópia e movimentação de arquivos.
- Listagem de arquivos.
- Criação e exclusão de diretórios.
- Consulta do tamanho de arquivos.
- Normalização e validação de caminhos.
- Tratamento de erros por exceções.
- Integração independente do Gatovel Framework.

## Requisitos

- PHP 8.3 ou superior, conforme a restrição `^8.3`.
- Composer.
- AWS SDK for PHP 3.x, somente para utilização do driver S3.

## Instalação

Após a publicação do pacote no Packagist:

```bash
composer require gatovel/storage
```

Para utilizar o driver Amazon S3, instale também:

```bash
composer require aws/aws-sdk-php:^3.0
```

O AWS SDK é opcional. Aplicações que utilizam apenas armazenamento local não precisam instalá-lo.

## Estrutura do pacote

```text
gatovel-storage/
├── src/
│   ├── contracts/
│   │   └── StorageInterface.php
│   ├── drivers/
│   │   ├── LocalDriver.php
│   │   └── S3Driver.php
│   ├── exceptions/
│   │   └── StorageException.php
│   ├── support/
│   │   └── PathNormalizer.php
│   └── StorageManager.php
├── tests/
├── composer.json
├── LICENSE
└── README.md
```

## Armazenamento local

O `LocalDriver` permite armazenar arquivos diretamente no sistema de arquivos do servidor.

### Inicialização

```php
<?php

require 'vendor/autoload.php';

use Gatovel\Storage\drivers\LocalDriver;

$storage = new LocalDriver(
    __DIR__ . '/storage'
);
```

### Criar um arquivo

```php
$storage->put(
    'documents/example.txt',
    'Hello Gatovel!'
);
```

### Ler um arquivo

```php
$content = $storage->get(
    'documents/example.txt'
);

echo $content;
```

### Verificar existência

```php
if ($storage->exists('documents/example.txt')) {
    echo 'Arquivo encontrado!';
}
```

### Consultar tamanho

```php
$size = $storage->size(
    'documents/example.txt'
);

echo "Tamanho: {$size} bytes";
```

### Copiar um arquivo

```php
$storage->copy(
    'documents/example.txt',
    'documents/copy.txt'
);
```

### Mover um arquivo

```php
$storage->move(
    'documents/copy.txt',
    'documents/moved.txt'
);
```

### Listar arquivos

```php
$files = $storage->files('documents');

foreach ($files as $file) {
    echo $file . PHP_EOL;
}
```

### Criar um diretório

```php
$storage->makeDirectory('uploads/images');
```

### Excluir um arquivo

```php
$storage->delete('documents/moved.txt');
```

### Excluir um diretório

```php
$storage->deleteDirectory('uploads/images');
```

**Atenção:** `deleteDirectory()` pode excluir o conteúdo do diretório. Utilize esse método com cuidado.

## Amazon S3

O `S3Driver` permite armazenar arquivos em serviços compatíveis com a API do Amazon S3.

### Configuração

```php
<?php

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Gatovel\Storage\drivers\S3Driver;

$client = new S3Client([
    'version' => 'latest',
    'region' => getenv('AWS_REGION') ?: 'us-east-1',
]);

$storage = new S3Driver(
    $client,
    getenv('AWS_BUCKET'),
    'application'
);
```

O terceiro argumento define um prefixo opcional para organizar os objetos dentro do bucket.

### Criar um arquivo

```php
$storage->put(
    'documents/example.txt',
    'Hello Amazon S3!'
);
```

### Ler um arquivo

```php
$content = $storage->get(
    'documents/example.txt'
);
```

### Verificar existência

```php
$exists = $storage->exists(
    'documents/example.txt'
);
```

### Listar arquivos

```php
$files = $storage->files('documents');

foreach ($files as $file) {
    echo $file . PHP_EOL;
}
```

### Excluir um arquivo

```php
$storage->delete('documents/example.txt');
```

### Credenciais AWS

Utilize os mecanismos de autenticação suportados pelo AWS SDK, como variáveis de ambiente, perfis de credenciais ou IAM Roles.

Exemplo de variáveis:

```dotenv
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_REGION=us-east-1
AWS_BUCKET=
```

Nunca armazene credenciais reais diretamente no código-fonte ou em arquivos versionados.

## Contrato de armazenamento

Os drivers implementam `StorageInterface`, responsável por estabelecer as operações disponíveis.

| Método | Descrição | Retorno |
|---|---|---|
| `put($path, $contents)` | Grava um arquivo | `void` |
| `get($path)` | Lê um arquivo | `string` |
| `exists($path)` | Verifica a existência | `bool` |
| `delete($path)` | Exclui um arquivo | `void` |
| `copy($source, $destination)` | Copia um arquivo | `void` |
| `move($source, $destination)` | Move um arquivo | `void` |
| `files($directory)` | Lista arquivos | `array` |
| `makeDirectory($path)` | Cria um diretório | `void` |
| `deleteDirectory($path)` | Exclui um diretório | `void` |
| `size($path)` | Consulta o tamanho | `int` |

A utilização de uma interface comum permite desenvolver outros drivers sem alterar o contrato principal.

## Tratamento de exceções

As falhas de armazenamento são representadas por `StorageException`.

```php
<?php

use Gatovel\Storage\exceptions\StorageException;

try {
    $content = $storage->get('missing.txt');
} catch (StorageException $exception) {
    echo $exception->getMessage();
}
```

Exemplos de situações que podem produzir exceções:

- Arquivo inexistente.
- Falha de leitura ou escrita.
- Falha de cópia ou movimentação.
- Caminho inválido.
- Falha na criação ou exclusão de diretórios.
- Erros de comunicação com o serviço de armazenamento.

## Segurança

O Gatovel Storage possui validações para reduzir riscos associados à manipulação de caminhos.

### Normalização de caminhos

O `PathNormalizer` rejeita entradas inseguras, incluindo:

- Tentativas de path traversal com `..`.
- Caminhos absolutos.
- Separadores de caminho não permitidos.
- Bytes nulos.
- Caracteres de controle.

### Proteção no armazenamento local

O `LocalDriver` verifica links simbólicos durante a resolução dos caminhos, evitando que symlinks estáticos sejam utilizados para acessar arquivos fora da raiz configurada nos cenários testados.

**Limitação:** essas verificações não garantem proteção absoluta contra alterações concorrentes no sistema de arquivos, incluindo condições de corrida do tipo TOCTOU.

Recomenda-se utilizar um diretório de armazenamento dedicado, com permissões restritas, e impedir que processos não confiáveis modifiquem sua estrutura.

### Segurança no Amazon S3

O driver S3 trabalha com objetos e prefixos, e não com diretórios tradicionais.

Recomenda-se:

- Aplicar políticas IAM com privilégios mínimos.
- Restringir o acesso aos buckets.
- Utilizar credenciais temporárias quando possível.
- Não expor credenciais no código-fonte.
- Avaliar cuidadosamente operações de exclusão em lote.

## Testes

O projeto possui testes para:

- `LocalDriver`.
- `S3Driver`.
- `StorageManager`.
- Segurança de caminhos.
- Proteção contra symlinks estáticos.
- Operações com arquivos inexistentes.
- Cópia e movimentação.
- Exclusão de múltiplos objetos S3.
- Falhas parciais nas operações S3.
- Consistência de comportamento entre drivers.

Os testes estão localizados em `tests/`.

### Executar testes locais

```bash
php tests/LocalDriverTest.php
php tests/StorageManagerTest.php
php tests/LocalSecurityBoundaryTest.php
```

### Executar testes S3

Os testes S3 requerem um endpoint compatível com Amazon S3, como o LocalStack, e um bucket de testes configurado.

```bash
php tests/S3DriverTest.php
php tests/S3SecurityTest.php
php tests/S3MoveExistsTest.php
php tests/S3BatchDeleteTest.php
php tests/S3PartialFailureTest.php
php tests/FilesContractTest.php
```

Os scripts de teste atuais utilizam configurações específicas do ambiente de desenvolvimento e podem precisar de ajustes para execução em outros ambientes.

## Independência do framework

O Gatovel Storage não depende do núcleo do Gatovel Framework.

Ele pode ser integrado a:

- Aplicações PHP independentes.
- APIs REST.
- Sistemas de gerenciamento de conteúdo.
- Aplicações de comércio eletrônico.
- Outros frameworks PHP.

## Versionamento

O projeto pretende seguir o padrão Semantic Versioning.

- **MAJOR:** alterações incompatíveis.
- **MINOR:** novas funcionalidades compatíveis.
- **PATCH:** correções compatíveis.

Primeira versão planejada: `v1.0.0`.

## Licença

Distribuído sob a licença MIT.

Consulte o arquivo [LICENSE](LICENSE).

## Ecossistema Gatovel

O `gatovel/storage` integra o ecossistema de bibliotecas modulares Gatovel, desenvolvido com foco em desacoplamento, reutilização e organização de código.

O pacote pode ser utilizado independentemente ou integrado ao Gatovel Framework.
