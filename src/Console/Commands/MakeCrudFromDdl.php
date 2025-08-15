<?php

namespace AlysonTrizotto\DdlCrud\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use AlysonTrizotto\DdlCrud\Generators\ControllerGenerator;
use AlysonTrizotto\DdlCrud\Generators\ResourceGenerator;
use AlysonTrizotto\DdlCrud\Generators\FactoryGenerator;
use AlysonTrizotto\DdlCrud\Generators\ServiceGenerator;
use AlysonTrizotto\DdlCrud\Generators\ModelGenerator;
use AlysonTrizotto\DdlCrud\Generators\RequestGenerator;
use AlysonTrizotto\DdlCrud\Generators\FeatureTestGenerator;
use AlysonTrizotto\DdlCrud\Generators\UnitTestGenerator;
use AlysonTrizotto\DdlCrud\Generators\MigrationGenerator;
use AlysonTrizotto\DdlCrud\Support\DdlParser;
use AlysonTrizotto\DdlCrud\Generators\RoutesGenerator;

class MakeCrudFromDdl extends Command
{
    /**
     * Build possible stub search paths for a given stub name.
     */
    protected function projectStubPaths(string $name): array
    {
        $root = base_path();
        return [
            $root . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'cascade' . DIRECTORY_SEPARATOR . $name . '.stub',
            $root . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . $name . '.stub',
        ];
    }

    /**
     * Return the contents of the first existing file in the given path list.
     */
    protected function readFirstExisting(array $paths): ?string
    {
        foreach ($paths as $p) {
            if (File::exists($p)) {
                return File::get($p);
            }
        }
        return null;
    }

    /**
     * Ensure directory exists, write file, and log a concise message.
     */
    protected function ensureDirAndWrite(string $path, string $content, string $label): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
        $this->info($label . ' criado: ' . $path);
    }

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:crud-from-ddl {domain?} {ddl?}
        {--no-routes}
        {--route-prefix=}
        {--route-name=}
        {--middleware=}
        {--name-prefix=}
        {--only=}
        {--except=}
        {--nested=}';

    /**
     * The console command description.
     */
    protected $description = 'Gera migrations, models, services, controllers e requests a partir de uma DDL SQL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Read domain from arg or prompt (CamelCase)
        $domainInput = $this->argument('domain') ?? $this->ask('Informe o domínio (CamelCase)');
        if (!$domainInput || trim($domainInput) === '') {
            $this->error('Domínio é obrigatório.');
            return self::FAILURE;
        }

        $domain = Str::studly(trim($domainInput));

        // Read DDL path from arg or prompt
        $ddlPath = $this->argument('ddl') ?? $this->ask('Caminho do arquivo DDL (.sql)');
        if (!$ddlPath || !File::exists($ddlPath)) {
            $this->error('Arquivo DDL não encontrado: ' . ($ddlPath ?? ''));
            return self::FAILURE;
        }
        $ddl = File::get($ddlPath);

        // Parse tables from DDL (delegated to parser)
        $tables = (new DdlParser())->parseCreateTables($ddl);
        if (empty($tables)) {
            $this->error('Nenhuma tabela encontrada na DDL.');
            return self::FAILURE;
        }

        // Generate artifacts for each table
        foreach ($tables as $table) {
            $this->generateForTable($domain, $table);
        }

        $this->info('Scaffold gerado com sucesso.');
        return self::SUCCESS;
    }

    /**
     * Load a custom stub from stubs/cascade, returning null if not found.
     */
    protected function getStub(string $name): ?string
    {
        return $this->readFirstExisting($this->projectStubPaths($name));
    }

    protected function generateForTable(string $domain, array $tableDef): void
    {
        $schema = $tableDef['schema'];
        $table = $tableDef['table'];
        $full = $tableDef['full'];

        // 1) Migration
        $this->makeMigration($schema, $table, $tableDef);

        // 2) Model
        $modelClass = Str::studly(Str::singular($table));
        $this->makeModel($domain, $modelClass, $full, $tableDef);

        // 3) Service
        $this->makeService($domain, $modelClass);

        // 4) Requests
        $this->makeRequests($domain, $modelClass, $tableDef);

        // 5) Resource
        $this->makeResource($domain, $modelClass, $tableDef);

        // 6) Controller
        $this->makeController($domain, $modelClass);

        // 6.1) API Routes (idempotent append into routes/api.php)
        if (!$this->option('no-routes')) {
            $prefix = (string) ($this->option('route-prefix') ?? '');
            $slugOverride = $this->option('route-name') ? (string) $this->option('route-name') : null;
            $middlewares = null;
            if ($this->option('middleware')) {
                $mwRaw = (string) $this->option('middleware');
                // Split only by '|' or ';' to avoid breaking params like throttle:60,1
                $parts = preg_split('/[|;]+/', $mwRaw) ?: [];
                $parts = array_values(array_filter(array_map('trim', $parts)));
                $middlewares = !empty($parts) ? $parts : [trim($mwRaw)];
            }
            $namePrefix = $this->option('name-prefix') ? (string) $this->option('name-prefix') : null;
            $only = $this->option('only')
                ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))))
                : null;
            $except = $this->option('except')
                ? array_values(array_filter(array_map('trim', explode(',', (string) $this->option('except')))))
                : null;

            $nested = $this->option('nested') ? (string) $this->option('nested') : null;
            $this->appendApiRoute($domain, $modelClass, $table, $prefix, $slugOverride, $middlewares, $namePrefix, $only, $except, $nested);
        }

        // 7) Factory
        $this->makeFactory($domain, $modelClass, $tableDef);

        // 8) Tests (Unit + Feature)
        $this->makeTests($domain, $modelClass, $table, $tableDef);
    }

    protected function makeMigration(?string $schema, string $table, array $tableDef): void
    {
        $path = (new MigrationGenerator())->generate('', '', compact('schema','table','tableDef'));
        $this->info('Migration criada: ' . $path);
    }


    protected function makeModel(string $domain, string $modelClass, string $fullTableName, array $tableDef): void
    {
        $path = (new ModelGenerator())->generate($domain, $modelClass, compact('tableDef','fullTableName'));
        $this->info('Model criado: ' . $path);
    }

    protected function detectPrimaryKey(array $tableDef): ?string
    {
        foreach ($tableDef['columns'] as $col) {
            if (str_contains(strtolower($col['raw']), 'primary key')) {
                return $col['name'];
            }
        }
        return null;
    }

    protected function makeService(string $domain, string $modelClass): void
    {
        $path = (new ServiceGenerator())->generate($domain, $modelClass);
        $this->info('Service criado: ' . $path);
    }

    protected function makeRequests(string $domain, string $modelClass, array $tableDef): void
    {
        $path = (new RequestGenerator())->generate($domain, $modelClass, compact('tableDef'));
        $this->info('Requests criados em: ' . dirname($path));
    }

    // Request generation delegated to RequestGenerator

    protected function makeController(string $domain, string $modelClass): void
    {
        $path = (new ControllerGenerator())->generate($domain, $modelClass);
        $this->info('Controller criado: ' . $path);
    }

    protected function makeResource(string $domain, string $modelClass, array $tableDef): void
    {
        $path = (new ResourceGenerator())->generate($domain, $modelClass, compact('tableDef'));
        $this->info('Resource criado: ' . $path);
    }

    protected function makeFactory(string $domain, string $modelClass, array $tableDef): void
    {
        $path = (new FactoryGenerator())->generate($domain, $modelClass, compact('tableDef'));
        $this->info('Factory criado: ' . $path);
    }

    protected function makeTests(string $domain, string $modelClass, string $table, array $tableDef): void
    {
        // Delegate to Unit and Feature test generators
        $unitPath = (new UnitTestGenerator())->generate($domain, $modelClass, compact('table','tableDef'));
        $featurePath = (new FeatureTestGenerator())->generate($domain, $modelClass, compact('table','tableDef'));
        $this->info('Unit tests criados em: ' . dirname($unitPath));
        $this->info('Feature tests criados em: ' . dirname($featurePath));
    }

    protected function appendApiRoute(
        string $domain,
        string $modelClass,
        string $table,
        string $prefix = '',
        ?string $slugOverride = null,
        ?array $middlewares = null,
        ?string $namePrefix = null,
        ?array $only = null,
        ?array $except = null,
        ?string $nestedPath = null
    ): void
    {
        $path = (new RoutesGenerator())->appendApiRoute(
            $domain,
            $modelClass,
            $table,
            $prefix !== '' ? $prefix : null,
            $slugOverride,
            $middlewares,
            $namePrefix,
            $only,
            $except,
            $nestedPath
        );
        $this->info('API route adicionada em: ' . $path);
    }
}
