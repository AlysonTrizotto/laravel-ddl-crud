# Gerador de Scaffold a partir de DDL (Laravel 12)

Este projeto possui um comando Artisan que gera um CRUD completo (Migration, Model, Service, Requests, Resource e Controller API) a partir de um arquivo DDL (.sql).

- Framework: Laravel 12
- Padrão de código: conforme `documento_base_stubs.md`
- Geração baseada em stubs personalizáveis em `stubs/cascade/`

## Pré-requisitos
- PHP e extensões do Laravel 12
- Projeto Laravel instalado e funcional
- Arquivo DDL (.sql) com instruções `CREATE TABLE ... (...);`

## Publicar/Customizar Stubs (opcional)
Os stubs já foram preparados neste projeto em `stubs/cascade/`, mas você pode publicá-los e ajustá-los conforme necessidade:

```bash
php artisan stub:publish
```

Stubs utilizados pelo gerador (customizados):
- `stubs/cascade/model.stub`
- `stubs/cascade/service.stub`
- `stubs/cascade/request.stub`
- `stubs/cascade/resource.stub`
- `stubs/cascade/controller.api.stub`

Se algum stub não existir em `stubs/cascade/`, o gerador usa um template interno de fallback.

## Uso
Execute o comando e informe o domínio (CamelCase) e o caminho do arquivo DDL:

```bash
php artisan make:crud-from-ddl
```

Prompts:
- Domínio (CamelCase) — exemplo: `Checklist` ou `Trip`.
- Caminho do arquivo DDL — exemplo: `D:/Alyson/ddl/checklist.sql`.

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
  - `use SoftDeletes;`
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
O gerador cria apenas as classes. Registre as rotas manualmente, por exemplo em `routes/api.php`:

```php
use App\Http\Controllers\API\Checklist\PhotoAnnotationController;

Route::apiResource('photo-annotations', PhotoAnnotationController::class);
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

## Problemas comuns
- "Command cannot have an empty name": confira a assinatura do comando `make:crud-from-ddl` em `app/Console/Commands/MakeCrudFromDdl.php`.
- Arquivo DDL inválido: certifique-se de terminar cada `CREATE TABLE ... (...);` com `;` e usar sintaxe consistente.

---
Se precisar, abra uma issue ou peça para ajustar os stubs para novas regras do seu domínio.
