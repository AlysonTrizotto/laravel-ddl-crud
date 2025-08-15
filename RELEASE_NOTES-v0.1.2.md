
# v0.1.2 — Release Notes

## Novidades

- **Geração de rotas flexível** no comando `make:crud-from-ddl`:
	- Opções: `--route-prefix=`, `--route-name=`, `--middleware=`, `--name-prefix=`, `--only=`, `--except=`, `--nested=`
- **Geração idempotente de rotas** em `routes/api.php` com marcadores por domínio:
	- `// BEGIN: DDL-CRUD routes [Domain] ... // END: DDL-CRUD routes [Domain]`
- **Novo comando para limpeza de rotas geradas**:
	- `php artisan ddl-crud:routes:remove {Domain}`
- **Publicação de stubs** do pacote em `stubs/cascade/` para personalização dos templates.
- **Geração completa a partir de DDL**:
	- Migrations, Models, Services, Requests, Resources, Controllers, Factories, Tests (unit/feature).

## Melhorias

- Normalização de slug/segmento de rota a partir do nome da tabela (`kebab-case`).
- Criação segura de `routes/api.php` caso não exista.
- Parsers de middleware preservam parâmetros com vírgula (ex.: `throttle:60,1`), aceitando separadores `|` ou `;`.
- Organização do código e logs de criação mais claros.

## Correções

- Ajustes no auto-discovery do Service Provider do pacote.
- Pequenos fixes em geradores e tratamento de caminhos.

## Quebra de Compatibilidade

- Nenhuma esperada.

---

**Resumo:**  
Esta versão adiciona opções ricas de geração de rotas, um removedor de rotas, stubs publicáveis e várias melhorias de robustez na geração a partir de DDL.