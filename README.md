# Laravel DDL CRUD Generator

Gere um CRUD completo (Migration, Model, Service, Requests, Resource, Controller API, Factory e Testes) a partir de um arquivo DDL (.sql) no Laravel 12.

- Framework alvo: Laravel 12
- Stubs personalizáveis: `stubs/cascade/`
- Código organizado por geradores dedicados (SRP) e utilitários de suporte

## Recursos
- __Entrada via DDL__: lê múltiplas `CREATE TABLE` no mesmo arquivo
- __Geração completa__: migrations, models, services, requests, resources, controllers, factories, testes unit/feature
- __Stubs sobrescrevíveis__: personalize a estrutura gerada publicando os stubs
- __Heurísticas úteis__: mapeamento de tipos, inferência de `fillable`, `casts`, validações básicas
- __Separação de responsabilidades__: parsing, geração e escrita extraídos para classes dedicadas

## Sumário
- [Pré-requisitos](#pré-requisitos)
- [Instalação](#instalação)
- [Publicar stubs (opcional)](#publicar-stubs-opcional)
- [Uso](#uso)
- [Exemplo de DDL suportada](#exemplo-de-ddl-suportada-postgres-like-ou-mysql-simples)
- [O que é gerado (por tabela)](#o-que-é-gerado-por-tabela)
- [Rotas](#rotas)
- [Customização](#customização)
- [Testes](#testes)
- [Solução de problemas](#solução-de-problemas)
- [Licença](#licença)

## Pré-requisitos
- PHP e extensões do Laravel 12
- Projeto Laravel instalado e funcional
- Arquivo DDL (.sql) com instruções `CREATE TABLE ... (...);`

## Instalação

Instale via Composer:

```bash
composer require alysontrizotto/laravel-ddl-crud
```

Este pacote suporta auto-discovery no Laravel. O service provider exposto é
`AlysonTrizotto\DdlCrud\DdlCrudServiceProvider` (wrapper que aponta para
`AlysonTrizotto\DdlCrud\Providers\DdlCrudServiceProvider`).

## Publicar stubs (opcional)

Publique os stubs para customização no seu projeto (tag `stubs`):

```bash
php artisan vendor:publish --tag=stubs
```

Os stubs serão publicados em `stubs/cascade/` na raiz do projeto.

Stubs utilizados pelo gerador (se não existirem no projeto, um fallback interno será usado):
- `stubs/cascade/model.stub`
- `stubs/cascade/service.stub`
- `stubs/cascade/request.stub`
- `stubs/cascade/resource.stub`
- `stubs/cascade/controller.api.stub`
- `stubs/cascade/factory.stub`
- `stubs/cascade/unit.model.test.stub`
- `stubs/cascade/unit.service.test.stub`
- `stubs/cascade/feature.controller.test.stub`

## Uso
Você pode executar de duas formas:

1) Interativo (responder aos prompts):

```bash
php artisan make:crud-from-ddl
```

Prompts:
- Domínio (CamelCase) — exemplo: `Checklist` ou `Trip`.
- Caminho do arquivo DDL — exemplo: `D:/Alyson/ddl/checklist.sql`.

2) Direto por argumentos (sem prompts):

```bash
php artisan make:crud-from-ddl Checklist D:/Alyson/ddl/checklist.sql
```

## Exemplo de DDL suportada (Postgres-like ou MySQL simples)
```sql
CREATE TABLE checklist.photo_annotations (
  id uuid primary key,
  checklist_id uuid not null,
  label varchar(150) not null,
  metadata jsonb,
  created_at timestamptz,
  updated_at timestamptz
);
```

O parser identifica o schema opcional (`checklist`), o nome da tabela (`photo_annotations`), colunas, tipos, nulos e chave primária.

## O que é gerado (por tabela)
- Migration em `database/migrations/*_create_{schema_}{tabela}_table.php`
  - Cria schema (se informado) e a tabela com colunas mapeadas a partir da DDL
- Model em `app/Models/{Domínio}/{Model}.php`
  - `use HasFactory;`
  - `use SoftDeletes;` (apenas se a DDL contiver `deleted_at`)
  - `$table`, `$primaryKey`, `$incrementing`, `$keyType`
  - `$fillable` (exclui `created_at`, `updated_at`, `deleted_at`)
  - `$casts` (json/jsonb/arrays mapeados para `array`)
  - Métodos: `store(array $data)` e `applyUpdate(array $data)`
  - `scopeFilter(array $filters)`
- Service em `app/Services/{Domínio}/{Model}Service.php`
  - Focado em regra de negócio
  - Usa `Model::store`, `$model->applyUpdate`, `$model->delete()`
  - Métodos: `paginate`, `find`, `create`, `update`, `delete`
- Requests em `app/Http/Requests/{Domínio}/{Model}/`
  - `Store{Model}Request.php` e `Update{Model}Request.php`
  - Regras inferidas da DDL (required, tipos básicos, unique quando aplicável)
- Resource em `app/Http/Resources/{Domínio}/{Model}Resource.php`
  - Constrói a resposta padronizada com os campos da tabela
- Controller API em `app/Http/Controllers/API/{Domínio}/{Model}Controller.php`
  - Endpoints: `index`, `store`, `show`, `update`, `destroy`
  - Retorna `Resource` nas respostas
- Factory em `database/factories/{Domínio}/{Model}Factory.php`
  - Namespace: `Database\\Factories\\{Domínio}`
  - `$model` usando classe importada
  - PHPDoc `@extends Factory<{Model}>` usando nome curto
- Testes
  - Unit (Model): valida configuração do model
  - Unit (Service): cobre paginação e fluxo CRUD com asserts no banco
  - Feature (Controller): cobre CRUD completo via HTTP, status codes corretos, usa `apiResource`

## Exemplo de execução
```bash
php artisan make:crud-from-ddl
# Informe o domínio: Checklist
# Informe o caminho da DDL: D:/Alyson/sql/checklist_tables.sql
```

Arquivos esperados (exemplo `Checklist` + tabela `photo_annotations`):
- `database/migrations/2025_08_13_000000_create_checklist_photo_annotations_table.php`
- `app/Models/Checklist/PhotoAnnotation.php`
- `app/Services/Checklist/PhotoAnnotationService.php`
- `app/Http/Requests/Checklist/PhotoAnnotation/StorePhotoAnnotationRequest.php`
- `app/Http/Requests/Checklist/PhotoAnnotation/UpdatePhotoAnnotationRequest.php`
- `app/Http/Resources/Checklist/PhotoAnnotationResource.php`
- `app/Http/Controllers/API/Checklist/PhotoAnnotationController.php`

## Rotas
O gerador cria apenas as classes. Registre as rotas manualmente em `routes/api.php` e garanta que o arquivo seja carregado pelo Laravel 12:

```php
use App\Http\Controllers\API\Checklist\PhotoAnnotationController;

Route::apiResource('photo-annotations', PhotoAnnotationController::class);
```

No Laravel 12, certifique-se de que `bootstrap/app.php` está carregando `routes/api.php`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // ...
    ->create();
```

## Customização
Edite os stubs em `stubs/cascade/` para moldar o padrão do seu projeto:
- Adicionar/remover campos na resposta do `Resource`
- Ajustar validações nos `Requests`
- Adaptar `Service` para suas regras de negócio
- Evoluir o `Model` (relations, mutators, etc.)

## Observações
- O comando valida domínio (CamelCase) e lê múltiplas tabelas no mesmo arquivo DDL.
- Para tipos não suportados no Schema do Laravel, o gerador faz fallback para `string(36)` (ex.: uuid) e outros mapeamentos simples.
- Caso você utilize MySQL, ajuste os tipos/timezones nas DDLs conforme o seu banco.
- Fábricas são geradas por domínio: `database/factories/{Domínio}/{Model}Factory.php`. Isso permite ao Laravel resolver automaticamente a factory de models namespaced.
- O Controller usa vinculação de rota por nome de parâmetro; exemplo: para `PhotoAnnotationController`, as assinaturas são `show(PhotoAnnotation $photoAnnotation)`, `update(PhotoAnnotation $photoAnnotation, ...)`, `destroy(PhotoAnnotation $photoAnnotation)`.
- Em SQLite, colunas JSON podem ser persistidas como texto. Os testes gerados evitam comparar diretamente campos JSON com `assertDatabaseHas` para prevenir falsos negativos.
- Para `SoftDeletes`, inclua `deleted_at` na DDL para o gerador adicionar o trait ao Model e os asserts de soft delete aos testes. Caso seu schema inicial não tenha `deleted_at`, crie uma migration complementar adicionando `softDeletes()`.

## Testes
- Configure `.env.testing` e rode as migrations de teste.
- Execute a suíte: `php artisan test`.

---
Se precisar, abra uma issue ou peça para ajustar os stubs para novas regras do seu domínio.

## Problemas comuns
- "Command cannot have an empty name": confira a assinatura do comando `make:crud-from-ddl` em `app/Console/Commands/MakeCrudFromDdl.php`.
- Arquivo DDL inválido: certifique-se de terminar cada `CREATE TABLE ... (...);` com `;` e usar sintaxe consistente.
