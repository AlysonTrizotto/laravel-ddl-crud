# Release Notes

## v0.1.19 — 2025-08-14

### Highlights
- Ajuste crítico nas regras de validação para colunas JSON/JSONB nos Requests (evita HTTP 422 em campos como `metadata` e `attributes`).
- Migrações mais robustas: `down()` agora desabilita/reabilita FKs durante drop (mitiga erros no SQLite em testes).
- Correções de geração de migrações (interpolação de `$table`, aspas, ponto e vírgula em colunas terminais).
- Postman collection adicionada para testar rapidamente as rotas API do scaffold.

### Changes
- `src/Generators/RequestGenerator.php`
  - Mapeamento de tipos `json`/`jsonb` para regra de validação `array` (antes `string`).
  - Mantida inferência de `required|nullable` conforme `NOT NULL` e ajustes para tipos numéricos, datas e booleanos.
- `src/Generators/MigrationGenerator.php`
  - Geração de código com aspas simples e concatenação para evitar interpolação acidental de `$table`.
  - Garantido `;` ao final de colunas terminais (ex.: `$table->id('id');`).
  - `down()` envolve `Schema::dropIfExists()` com `Schema::disableForeignKeyConstraints()` / `enable...`.
- Postman
  - Nova collection: `scaffold/postman/Accounts API.postman_collection.json` contendo CRUD de `Customers`, `Products`, `Orders`, `Order Items`.

### Fixes
- Falhas de testes de Feature por validação incorreta de JSON: resolvido ao gerar Requests com `array` para colunas JSON/JSONB.
- Erros de FK ao dropar tabelas no SQLite durante testes: mitigado no `down()` das migrações geradas.
- Erros de sintaxe em migrações geradas devido a interpolação/aspas incorretas: corrigidos.

### Breaking Changes
- Nenhuma.

### Upgrade Guide
1. Atualize o pacote para >= v0.1.19 no seu projeto Laravel.
2. Regere os artefatos a partir do seu DDL para aplicar as novas regras de Request e migrações:
   ```bash
   php artisan make:crud-from-ddl <Domínio> <caminho_para_ddl>
   ```
3. Se já existirem migrações antigas para as mesmas tabelas, remova-as (ou rode `php artisan migrate:fresh`) para evitar duplicidade.
4. Rode a suíte de testes:
   ```bash
   php artisan test
   ```

### Test Environment
- `phpunit.xml` padrão usa SQLite em memória: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`.
- Para inspecionar dados pós-teste, use `.env.testing` com `DB_DATABASE=database/testing.sqlite` e crie o arquivo `database/testing.sqlite`.

### Postman Collection
- Caminho: `scaffold/postman/Accounts API.postman_collection.json`.
- Variável: `base_url` (padrão: `http://localhost`). Ajuste para seu ambiente (ex.: `http://localhost:8000`).

### Notes
- O gerador mantém SRP com generators dedicados (Model, Request, Migration, Controller, Service, Factory, Tests) e `Support\DdlParser` para parsing do DDL.
- Continuidade: centralizar heurísticas/constantes compartilhadas entre generators e ampliar cobertura de testes E2E.
