<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
class MakeCrudFromDdl extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:crud-from-ddl {domain?} {ddl?}';

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

        // Parse tables from DDL
        $tables = $this->parseCreateTables($ddl);
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
        $projectRoot = base_path();
        $paths = [
            $projectRoot . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'cascade' . DIRECTORY_SEPARATOR . $name . '.stub',
            $projectRoot . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . $name . '.stub',
        ];
        foreach ($paths as $p) {
            if (File::exists($p)) {
                return File::get($p);
            }
        }
        return null;
    }

    /**
     * Parse CREATE TABLE statements.
     * Supports schema-qualified names and common Postgres types in the sample.
     */
    protected function parseCreateTables(string $ddl): array
    {
        $results = [];
        // Split by CREATE TABLE ... (...);
        $pattern = '/CREATE\s+TABLE\s+([\w\.]+)\s*\((.*?)\);/ims';
        if (preg_match_all($pattern, $ddl, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $fullName = trim($m[1]); // e.g. checklist.photo_annotations
                $body = trim($m[2]);
                $columns = [];
                $constraints = [];
                $indexes = [];

                // Split lines by commas, but be gentle with parentheses
                $parts = $this->smartSplit($body);
                foreach ($parts as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    if (stripos($line, 'constraint ') === 0 || stripos($line, 'foreign key') === 0) {
                        $constraints[] = $line;
                        continue;
                    }
                    // Column definition
                    if (preg_match('/^\"?(\w+)\"?\s+([\w\[\]\(\), ]+)(.*)$/i', $line, $cm)) {
                        $col = [
                            'name' => $cm[1],
                            'type' => trim($cm[2]),
                            'raw'  => $line,
                        ];
                        $columns[] = $col;
                    }
                }

                $schema = null; $table = $fullName;
                if (str_contains($fullName, '.')) {
                    [$schema, $table] = explode('.', $fullName, 2);
                }

                $results[] = [
                    'schema' => $schema,
                    'table' => $table,
                    'full' => $fullName,
                    'columns' => $columns,
                    'constraints' => $constraints,
                    'indexes' => $indexes,
                ];
            }
        }
        // Parse standalone CREATE INDEX statements
        $idxPattern = '/CREATE\s+(UNIQUE\s+)?INDEX\s+([\w]+)\s+ON\s+([\w\.]+)\s*\(([^\)]+)\);/ims';
        if (preg_match_all($idxPattern, $ddl, $ims, PREG_SET_ORDER)) {
            foreach ($ims as $im) {
                $unique = trim($im[1]) !== '';
                $idxName = trim($im[2]);
                $tbl = trim($im[3]);
                $cols = array_map(fn($s) => trim(str_replace('"','', $s)), explode(',', trim($im[4])));
                foreach ($results as &$r) {
                    if ($r['full'] === $tbl || $r['table'] === $tbl) {
                        $r['indexes'][] = [
                            'name' => $idxName,
                            'columns' => $cols,
                            'unique' => $unique,
                        ];
                        break;
                    }
                }
                unset($r);
            }
        }
        return $results;
    }

    protected function smartSplit(string $body): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];
            if ($ch === '(') { $depth++; }
            if ($ch === ')') { $depth--; }
            if ($ch === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $ch;
        }
        if (trim($current) !== '') $parts[] = $current;
        return $parts;
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

        // 7) Factory
        $this->makeFactory($domain, $modelClass, $tableDef);

        // 8) Tests (Unit + Feature)
        $this->makeTests($domain, $modelClass, $table, $tableDef);
    }

    protected function makeMigration(?string $schema, string $table, array $tableDef): void
    {
        $timestamp = date('Y_m_d_His');
        $file = base_path('database/migrations/' . $timestamp . '_create_' . ($schema ? $schema . '_' : '') . $table . '_table.php');
        $class = 'Create' . Str::studly(($schema ? $schema . '_' : '') . $table) . 'Table';

        $lines = [];
        $lines[] = "<?php";
        $lines[] = "";
        $lines[] = "use Illuminate\\Database\\Migrations\\Migration;";
        $lines[] = "use Illuminate\\Database\\Schema\\Blueprint;";
        $lines[] = "use Illuminate\\Support\\Facades\\Schema;";
        $lines[] = "use Illuminate\\Support\\Facades\\DB;";
        $lines[] = "";
        $lines[] = "return new class extends Migration {";
        $lines[] = "    public function up(): void";
        $lines[] = "    {";
        if ($schema) {
            $lines[] = "        DB::statement(\"CREATE SCHEMA IF NOT EXISTS {$schema}\");";
        }
        $tableNameLiteral = $schema ? "$schema.$table" : $table;
        $lines[] = "        Schema::create('{$tableNameLiteral}', function (Blueprint \$table) {";

        foreach ($tableDef['columns'] as $col) {
            $lines[] = '            ' . $this->columnToBlueprint($col);
        }

        // Foreign keys from constraints
        foreach ($tableDef['constraints'] as $constraint) {
            $c = strtolower(trim($constraint));
            if (preg_match('/foreign\s+key\s*\(([^\)]+)\)\s*references\s+([\w\.]+)\s*\(([^\)]+)\)/i', $c, $m)) {
                $cols = array_map('trim', explode(',', $m[1]));
                $refTable = trim($m[2]);
                $refCols = array_map('trim', explode(',', $m[3]));
                if (count($cols) === 1 && count($refCols) === 1) {
                    $col = $cols[0];
                    $refCol = $refCols[0];
                    $lines[] = "            \$table->foreign('{$col}')->references('{$refCol}')->on('{$refTable}');";
                }
            }
        }

        // Indexes
        if (!empty($tableDef['indexes'])) {
            foreach ($tableDef['indexes'] as $idx) {
                $colsList = "['" . implode("','", $idx['columns']) . "']";
                if (!empty($idx['unique'])) {
                    $lines[] = "            \$table->unique({$colsList}, '{$idx['name']}');";
                } else {
                    $lines[] = "            \$table->index({$colsList}, '{$idx['name']}');";
                }
            }
        }

        $lines[] = "        });";
        $lines[] = "    }";
        $lines[] = "";
        $lines[] = "    public function down(): void";
        $lines[] = "    {";
        $lines[] = "        Schema::dropIfExists('{$tableNameLiteral}');";
        $lines[] = "    }";
        $lines[] = "};";

        File::put($file, implode(PHP_EOL, $lines));
        $this->info("Migration criada: " . $file);
    }

    protected function columnToBlueprint(array $col): string
    {
        $name = $col['name'];
        $type = strtolower($col['type']);
        $raw = strtolower($col['raw']);

        // Detect nullability
        $nullable = str_contains($raw, ' not null') ? false : (str_contains($raw, ' null') ? true : true);

        // Defaults
        $default = null;
        if (preg_match('/default\s+([^\s,]+)/', $raw, $dm)) {
            $default = trim($dm[1]);
        }

        // Map types
        $method = null; $args = '';
        if (str_starts_with($type, 'uuid')) {
            $method = 'uuid';
        } elseif (preg_match('/^char\((\d+)\)/', $type, $cm)) {
            // If looks like UUID field and len is 36, use uuid(); else string with length
            if ($cm[1] == '36' && str_contains($name, 'uuid')) {
                $method = 'uuid';
            } else {
                $method = "string('{$name}', {$cm[1]})";
            }
        } elseif (preg_match('/^decimal\((\d+),(\d+)\)/', $type, $dm)) {
            $method = "decimal('{$name}', {$dm[1]}, {$dm[2]})";
        } elseif (str_starts_with($type, 'double')) {
            $method = "double('{$name}')";
        } elseif (str_starts_with($type, 'float')) {
            $method = "float('{$name}')";
        } elseif ($type === 'date' || str_starts_with($type, 'date ')) {
            $method = "date('{$name}')";
        } elseif ($type === 'datetime' || str_starts_with($type, 'datetime')) {
            $method = "dateTime('{$name}')";
        } elseif (str_starts_with($type, 'timestamptz')) {
            $method = "timestampTz('{$name}', 6)";
        } elseif (str_starts_with($type, 'timestamp')) {
            $method = "timestamp('{$name}')";
        } elseif (str_starts_with($type, 'jsonb') || str_starts_with($type, 'json')) {
            $method = 'json';
        } elseif (str_starts_with($type, 'text')) {
            $method = 'text';
        } elseif (preg_match('/varchar\((\d+)\)/', $type, $tm)) {
            $method = "string('{$name}', {$tm[1]})";
        } elseif (str_starts_with($type, 'bigint')) {
            $method = 'bigInteger';
        } elseif ($type === 'int4' || $type === 'integer' || str_starts_with($type, 'int')) {
            $method = 'integer';
        } elseif (str_starts_with($type, 'boolean')) {
            $method = 'boolean';
        } elseif (str_starts_with($type, 'uuid[')) { // arrays
            // Fallback: json
            $method = 'json';
        } else {
            // Fallback: string
            $method = 'string';
        }

        if ($method === 'uuid') {
            $code = "\$table->uuid('{$name}')";
        } elseif ($method === 'json') {
            $code = "\$table->json('{$name}')";
        } elseif ($method === 'text') {
            $code = "\$table->text('{$name}')";
        } elseif ($method === 'boolean') {
            $code = "\$table->boolean('{$name}')";
        } elseif ($method === 'integer') {
            $code = "\$table->integer('{$name}')";
        } elseif ($method === 'bigInteger') {
            $code = "\$table->bigInteger('{$name}')";
        } elseif (str_starts_with($method, "string('")) {
            $code = "\$table->{$method}";
        } elseif (str_starts_with($method, "timestampTz('")) {
            $code = "\$table->{$method}";
        } elseif (str_starts_with($method, "timestamp('")) {
            $code = "\$table->{$method}";
        } else {
            // generic string fallback
            $code = "\$table->string('{$name}')";
        }

        // Primary key detection
        if (str_contains($raw, 'primary key')) {
            // If PK and uuid, we can set as primary
            if ($method === 'uuid') {
                $code .= "->primary()";
            } else {
                $code .= "->primary()";
            }
        }

        // Default handling
        if ($default) {
            // Normalize common defaults
            if ($default === 'now()') {
                $code .= "->useCurrent()";
            } elseif (preg_match('/^current_timestamp(\(\))?$/i', $default)) {
                $code .= "->useCurrent()";
            } elseif ($default === "gen_random_uuid()") {
                $code .= "->default(DB::raw('gen_random_uuid()'))";
            } elseif (preg_match("/^'(.*)'$/", $default)) {
                // Already quoted string literal
                $code .= "->default({$default})";
            } elseif (strtolower($default) === 'null') {
                // don't set default(null); nullable() will be applied
            } else {
                $code .= "->default({$default})";
            }
        }

        // Special case: soft deletes column
        if ($name === 'deleted_at') {
            return "\$table->softDeletes();";
        }

        // Unique constraint
        if (str_contains($raw, ' unique') && !str_contains($raw, 'primary key')) {
            $code .= "->unique()";
        }

        if ($nullable) {
            $code .= "->nullable()";
        }

        return $code . ';';
    }

    protected function makeModel(string $domain, string $modelClass, string $fullTableName, array $tableDef): void
    {
        $dir = app_path('Models/' . $domain);
        File::ensureDirectoryExists($dir);
        $path = $dir . '/' . $modelClass . '.php';

        $fillable = [];
        foreach ($tableDef['columns'] as $col) {
            $name = $col['name'];
            if (!in_array($name, ['created_at', 'updated_at', 'deleted_at'])) {
                $fillable[] = "'{$name}'";
            }
        }
        $casts = [];
        foreach ($tableDef['columns'] as $col) {
            $t = strtolower($col['type']);
            if (str_starts_with($t, 'json') || str_starts_with($t, 'jsonb') || str_starts_with($t, 'uuid[')) {
                $casts[] = "'{$col['name']}' => 'array'";
            }
            if (str_starts_with($t, 'uuid')) {
                $casts[] = "'{$col['name']}' => 'string'";
            }
        }

        $tableProp = "protected \$table = '" . $fullTableName . "';";
        $primaryKey = $this->detectPrimaryKey($tableDef) ?? 'id';
        $pkProp = "protected \$primaryKey = '" . $primaryKey . "';";
        // Assume non-incrementing string PK when PK is not integer-like
        $isUuidPk = false;
        foreach ($tableDef['columns'] as $col) {
            if ($col['name'] === $primaryKey && str_starts_with(strtolower($col['type']), 'uuid')) {
                $isUuidPk = true;
                break;
            }
        }
        $incProp = $isUuidPk ? 'public $incrementing = false;' : 'public $incrementing = true;';
        $keyType = $isUuidPk ? "protected \$keyType = 'string';" : "protected \$keyType = 'int';";

        $fillableExport = implode(",\n        ", $fillable);
        $castsExport = implode(",\n        ", $casts);

        $usesSoftDeletes = false;
        foreach ($tableDef['columns'] as $col) {
            if ($col['name'] === 'deleted_at') { $usesSoftDeletes = true; break; }
        }

        $template = $this->getStub('model') ?? <<<'PHP'
<?php

namespace __NAMESPACE__;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
__IMPORT_SOFT_DELETES__

class __MODEL__ extends Model
{
    use HasFactory__TRAIT_SOFT_DELETES__;

    __TABLE_PROP__
    __PK_PROP__
    __INC_PROP__
    __KEYTYPE_PROP__

    protected $fillable = [
        __FILLABLE__
    ];

    protected $casts = [
        __CASTS__
    ];

    // Basic filter scope example
    public function scopeFilter($query, array $filters)
    {
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') continue;
            $query->where($field, $value);
        }
        return $query;
    }
}
PHP;

        $replacements = [
            '__NAMESPACE__' => 'App\\Models\\' . $domain,
            '__MODEL__' => $modelClass,
            '__TABLE_PROP__' => $tableProp,
            '__PK_PROP__' => $pkProp,
            '__INC_PROP__' => $incProp,
            '__KEYTYPE_PROP__' => $keyType,
            '__FILLABLE__' => $fillableExport,
            '__CASTS__' => $castsExport,
            '__IMPORT_SOFT_DELETES__' => $usesSoftDeletes ? "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;" : '',
            '__TRAIT_SOFT_DELETES__' => $usesSoftDeletes ? ', SoftDeletes' : '',
        ];
        $content = str_replace(array_keys($replacements), array_values($replacements), str_replace('__TRAIT_SOFT_DELETES__', $replacements['__TRAIT_SOFT_DELETES__'], $template));

        File::put($path, $content);
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
        $dir = app_path('Services/' . $domain);
        File::ensureDirectoryExists($dir);
        $path = $dir . '/' . $modelClass . 'Service.php';
        $nsModel = "App\\Models\\{$domain}\\{$modelClass}";

        $template = $this->getStub('service') ?? <<<'PHP'
<?php

namespace App\\Services\\{$domain};

use {$nsModel};
use Illuminate\\Support\\Facades\\DB;

class {$modelClass}Service
{
    public function paginate(array \$filters = [], int \$perPage = 15)
    {
        return {$modelClass}::query()->filter(\$filters)->paginate(\$perPage);
    }

    public function create(array \$data): {$modelClass}
    {
        return DB::transaction(function() use (\$data) {
            return {$modelClass}::create(\$data);
        });
    }

    public function update({$modelClass} \$model, array \$data): {$modelClass}
    {
        return DB::transaction(function() use (\$model, \$data) {
            \$model->update(\$data);
            return \$model;
        });
    }

    public function delete({$modelClass} \$model): void
    {
        DB::transaction(function() use (\$model) {
            \$model->delete();
        });
    }
}
PHP;
        $content = str_replace(
            ['App\Services\{$domain}', '{$nsModel}', '{$modelClass}'],
            ['App\Services\\' . $domain, $nsModel, $modelClass],
            $template
        );
        File::put($path, $content);
        $this->info('Service criado: ' . $path);
    }

    protected function makeRequests(string $domain, string $modelClass, array $tableDef): void
    {
        $dir = app_path('Http/Requests/' . $domain . '/' . $modelClass);
        File::ensureDirectoryExists($dir);

        $rulesCreate = $this->inferRules($tableDef, true);
        $rulesUpdate = $this->inferRules($tableDef, false);

        $this->writeRequest($dir . '/Store' . $modelClass . 'Request.php', $domain, $modelClass, 'Store', $rulesCreate);
        $this->writeRequest($dir . '/Update' . $modelClass . 'Request.php', $domain, $modelClass, 'Update', $rulesUpdate);
    }

    protected function inferRules(array $tableDef, bool $isCreate): array
    {
        $rules = [];
        foreach ($tableDef['columns'] as $col) {
            $name = $col['name'];
            $raw = strtolower($col['raw']);
            $type = strtolower($col['type']);
            if (in_array($name, ['created_at','updated_at','deleted_at','created_by','updated_by','deleted_by'])) continue;

            $colRules = [];
            $required = str_contains($raw, ' not null') || str_contains($raw, 'primary key');
            if ($required && $isCreate) $colRules[] = 'required'; else $colRules[] = 'sometimes';

            if (str_starts_with($type, 'uuid')) {
                $colRules[] = 'uuid';
            } elseif (preg_match('/^char\((\d+)\)/', $type, $cm) && $cm[1] == '36' && str_contains($name, 'uuid')) {
                $colRules[] = 'uuid';
            } elseif (str_starts_with($type, 'json') || str_starts_with($type, 'jsonb') || str_starts_with($type, 'uuid[')) {
                $colRules[] = 'array';
            } elseif (str_starts_with($type, 'varchar')) {
                if (preg_match('/varchar\((\d+)\)/', $type, $m)) $colRules[] = 'string|max:' . $m[1]; else $colRules[] = 'string';
            } elseif (str_starts_with($type, 'text')) {
                $colRules[] = 'string';
            } elseif (str_starts_with($type, 'bigint') || str_starts_with($type, 'int')) {
                $colRules[] = 'integer';
            } elseif (str_starts_with($type, 'boolean')) {
                $colRules[] = 'boolean';
            } elseif ($type === 'date' || str_starts_with($type, 'date ')) {
                $colRules[] = 'date';
            } elseif ($type === 'datetime' || str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
                $colRules[] = 'date';
            } elseif (preg_match('/^decimal\(\d+,\d+\)/', $type) || str_starts_with($type, 'double') || str_starts_with($type, 'float')) {
                $colRules[] = 'numeric';
            } else {
                $colRules[] = 'string';
            }

            $rules[$name] = implode('|', $colRules);
        }
        return $rules;
    }

    protected function writeRequest(string $path, string $domain, string $modelClass, string $prefix, array $rules): void
    {
        $ns = "App\\Http\\Requests\\{$domain}" . "\\{$modelClass}";
        $class = $prefix . $modelClass . 'Request';
        // Pretty format rules with PSR-like indentation
        $lines = [];
        foreach ($rules as $k => $v) {
            $lines[] = "            '" . $k . "' => '" . $v . "',"; // 12 spaces
        }
        $rulesPretty = implode("\n", $lines);

        $template = $this->getStub('request') ?? <<<'PHP'
<?php

namespace {$ns};

use Illuminate\Foundation\Http\FormRequest;

class {$class} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
{$rulesPretty}
        ];
    }
}
PHP;
        $content = str_replace(['{$ns}', '{$class}', '{$rulesPretty}'], [$ns, $class, $rulesPretty], $template);
        File::put($path, $content);
        $this->info('Request criado: ' . $path);
    }

    protected function makeController(string $domain, string $modelClass): void
    {
        $dir = app_path('Http/Controllers/API/' . $domain);
        File::ensureDirectoryExists($dir);
        $path = $dir . '/' . $modelClass . 'Controller.php';

        $nsModel = "App\\Models\\{$domain}\\{$modelClass}";
        $nsService = "App\\Services\\{$domain}\\{$modelClass}Service";
        $nsStore = "App\\Http\\Requests\\{$domain}\\{$modelClass}\\Store{$modelClass}Request";
        $nsUpdate = "App\\Http\\Requests\\{$domain}\\{$modelClass}\\Update{$modelClass}Request";
        $nsResource = "App\\Http\\Resources\\{$domain}\\{$modelClass}Resource";
        $storeClass = "Store{$modelClass}Request";
        $updateClass = "Update{$modelClass}Request";

        $template = $this->getStub('controller.api') ?? <<<'PHP'
<?php

namespace App\Http\Controllers\API\{$domain};

use App\Http\Controllers\Controller;
use {$nsModel};
use {$nsService};
use {$nsStore};
use {$nsUpdate};
use Illuminate\Http\Request;
use {$nsResource};

class {$modelClass}Controller extends Controller
{
    public function __construct(private {$modelClass}Service \$service) {}

    public function index(Request \$request)
    {
        $data = \$this->service->paginate($request->all(), (int) $request->get('per_page', 15));
        $data->getCollection()->transform(fn($item) => new {$modelClass}Resource($item));
        return $data;
    }

    public function store({$nsStore} \$request)
    {
        $model = \$this->service->create($request->validated());
        return new {$modelClass}Resource($model);
    }

    public function show({$modelClass} \$model)
    {
        return new {$modelClass}Resource(\$model);
    }

    public function update({$modelClass} \$model, {$nsUpdate} \$request)
    {
        $model = \$this->service->update(\$model, \$request->validated());
        return new {$modelClass}Resource($model);
    }

    public function destroy({$modelClass} \$model)
    {
        \$this->service->delete(\$model);
        return response()->noContent();
    }
}
PHP;
        $content = str_replace(
            [
                'App\Http\Controllers\API\{$domain}',
                '{$nsModel}', '{$nsService}', '{$nsStore}', '{$nsUpdate}', '{$modelClass}', '{$nsResource}',
                '{$storeClass}', '{$updateClass}'
            ],
            [
                'App\Http\Controllers\API\\' . $domain,
                $nsModel, $nsService, $nsStore, $nsUpdate, $modelClass, $nsResource,
                $storeClass, $updateClass
            ],
            $template
        );
        File::put($path, $content);
        $this->info('Controller criado: ' . $path);
    }

    protected function makeResource(string $domain, string $modelClass, array $tableDef): void
    {
        $dir = app_path('Http/Resources/' . $domain);
        File::ensureDirectoryExists($dir);
        $path = $dir . '/' . $modelClass . 'Resource.php';

        $fields = array_map(fn($c) => $c['name'], $tableDef['columns']);
        $body = implode(",\n            ", array_map(fn($f) => "'{$f}' => \$this->{$f}", $fields));

        $template = $this->getStub('resource') ?? <<<'PHP'
<?php

namespace App\Http\Resources\__DOMAIN__;

use Illuminate\Http\Resources\Json\JsonResource;

class __MODEL__Resource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        return [
            __FIELDS__
        ];
    }
}
PHP;
        $content = str_replace(
            ['__DOMAIN__', '__MODEL__', '__FIELDS__'],
            [$domain, $modelClass, $body],
            $template
        );
        File::put($path, $content);
        $this->info('Resource criado: ' . $path);
    }

    protected function makeFactory(string $domain, string $modelClass, array $tableDef): void
    {
        $dir = base_path('database/factories/' . $domain);
        File::ensureDirectoryExists($dir);
        $path = $dir . '/' . $modelClass . 'Factory.php';

        $nsModel = "App\\Models\\{$domain}\\{$modelClass}";

        // Build attributes based on columns
        $attrs = [];
        foreach ($tableDef['columns'] as $col) {
            $name = $col['name'];
            $raw = strtolower($col['raw']);
            $type = strtolower($col['type']);
            if (in_array($name, ['created_at','updated_at','deleted_at'])) continue;

            if ($name === 'id' && str_starts_with($type, 'uuid')) {
                $attrs[] = "'id' => (string) \\Illuminate\\Support\\Str::uuid()";
                continue;
            }
            if (str_starts_with($type, 'uuid')) {
                $attrs[] = "'{$name}' => (string) \\Illuminate\\Support\\Str::uuid()";
            } elseif (preg_match('/varchar\\((\\d+)\\)/', $type, $m)) {
                $len = (int)$m[1];
                $attrs[] = "'{$name}' => fake()->text(" . min($len, 50) . ")";
            } elseif (str_starts_with($type, 'text')) {
                $attrs[] = "'{$name}' => fake()->sentence()";
            } elseif (str_starts_with($type, 'json') || str_starts_with($type, 'jsonb')) {
                $attrs[] = "'{$name}' => []";
            } elseif (str_starts_with($type, 'boolean')) {
                $attrs[] = "'{$name}' => fake()->boolean()";
            } elseif (str_starts_with($type, 'bigint') || str_starts_with($type, 'int')) {
                $attrs[] = "'{$name}' => fake()->numberBetween(1, 1000)";
            } elseif (str_starts_with($type, 'timestamptz') || str_starts_with($type, 'timestamp')) {
                // Let Eloquent handle timestamps if fillable
                // Skip explicit default
            } else {
                $attrs[] = "'{$name}' => fake()->word()";
            }
        }
        $attributes = implode(",\n            ", $attrs);

        $template = $this->getStub('factory') ?? <<<'PHP'
<?php

namespace Database\Factories\__DOMAIN__;

use __NS_MODEL__;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<__MODEL__>
 */
class __MODEL__Factory extends Factory
{
    protected $model = __NS_MODEL__::class;

    public function definition(): array
    {
        return [
            __ATTRS__
        ];
    }
}
PHP;
        $content = str_replace([
            '__NS_MODEL__','__MODEL__','__ATTRS__','__DOMAIN__'
        ], [
            $nsModel, $modelClass, $attributes, $domain
        ], $template);
        File::put($path, $content);
        $this->info('Factory criada: ' . $path);
    }

    protected function makeTests(string $domain, string $modelClass, string $table, array $tableDef): void
    {
        $unitDir = base_path('tests/Unit/' . $domain);
        $featureDir = base_path('tests/Feature/' . $domain);
        File::ensureDirectoryExists($unitDir);
        File::ensureDirectoryExists($featureDir);

        $nsModel = "App\\Models\\{$domain}\\{$modelClass}";
        $nsService = "App\\Services\\{$domain}\\{$modelClass}Service";
        $controllerNs = "App\\Http\\Controllers\\API\\{$domain}\\{$modelClass}Controller";
        $routeBase = Str::kebab(Str::pluralStudly($modelClass)); // e.g. photo-annotations

        // Unit: Model Test
        $modelTestPath = $unitDir . "/{$modelClass}ModelTest.php";
        $modelTestTemplate = $this->getStub('unit.model.test') ?? <<<'PHP'
<?php

namespace Tests\Unit\__DOMAIN__;

use PHPUnit\Framework\TestCase;
use __NS_MODEL__;

class __MODEL__ModelTest extends TestCase
{
    public function test_fillable_properties_exist(): void
    {
        $m = new __MODEL__();
        $this->assertIsArray($m->getFillable());
    }
}
PHP;
        $modelTest = str_replace(
            ['__DOMAIN__', '__NS_MODEL__', '__MODEL__'],
            [$domain, $nsModel, $modelClass],
            $modelTestTemplate
        );
        File::put($modelTestPath, $modelTest);
        $this->info('Unit test (Model) criado: ' . $modelTestPath);

        // Unit: Service Test
        $serviceTestPath = $unitDir . "/{$modelClass}ServiceTest.php";
        $serviceTestTemplate = $this->getStub('unit.service.test') ?? <<<'PHP'
<?php

namespace Tests\Unit\__DOMAIN__;

use PHPUnit\Framework\TestCase;
use __NS_SERVICE__;
use __NS_MODEL__;

class __MODEL__ServiceTest extends TestCase
{
    public function test_can_instantiate_service(): void
    {
        $s = new __MODEL__Service();
        $this->assertInstanceOf(__MODEL__Service::class, $s);
    }
}
PHP;
        $serviceTest = str_replace(
            ['__DOMAIN__', '__NS_SERVICE__', '__NS_MODEL__', '__MODEL__', '__TABLE__'],
            [
                $domain,
                $nsService,
                $nsModel,
                $modelClass,
                (isset($tableDef['schema']) && $tableDef['schema']) ? ($tableDef['schema'] . '.' . $table) : $table
            ],
            $serviceTestTemplate
        );
        File::put($serviceTestPath, $serviceTest);
        $this->info('Unit test (Service) criado: ' . $serviceTestPath);

        // Feature: Controller/API Test
        $featureTestPath = $featureDir . "/{$modelClass}ControllerTest.php";
        $featureTestTemplate = $this->getStub('feature.controller.test') ?? <<<'PHP'
<?php

namespace Tests\Feature\__DOMAIN__;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use __NS_MODEL__;
use Illuminate\Support\Str;

class __MODEL__ControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_ok(): void
    {
        __NS_MODEL__::factory()->count(2)->create();
        $res = $this->getJson('/api/__ROUTE_BASE__');
        $res->assertStatus(200);
    }

    public function test_store_validates_payload(): void
    {
        $res = $this->postJson('/api/__ROUTE_BASE__', []);
        $res->assertStatus(422);
    }

    public function test_store_creates_record(): void
    {
        $payload = __NS_MODEL__::factory()->make()->toArray();
        $res = $this->postJson('/api/__ROUTE_BASE__', $payload);
        $res->assertCreated()->assertJsonStructure(['data']);
        $this->assertDatabaseHas('__TABLE__', ['id' => $payload['id'] ?? null] + collect($payload)->only(array_keys($payload))->toArray());
    }

    public function test_show_returns_record(): void
    {
        $model = __NS_MODEL__::factory()->create();
        $res = $this->getJson('/api/__ROUTE_BASE__/' . $model->getKey());
        $res->assertOk()->assertJsonStructure(['data']);
    }

    public function test_update_modifies_record(): void
    {
        $model = __NS_MODEL__::factory()->create();
        $changes = __NS_MODEL__::factory()->make()->toArray();
        $res = $this->putJson('/api/__ROUTE_BASE__/' . $model->getKey(), $changes);
        $res->assertOk();
        foreach ($changes as $k => $v) {
            if (in_array($k, ['created_at','updated_at','deleted_at'])) continue;
            $this->assertDatabaseHas('__TABLE__', [$k => $v]);
        }
    }

    public function test_destroy_deletes_record(): void
    {
        $model = __NS_MODEL__::factory()->create();
        $res = $this->deleteJson('/api/__ROUTE_BASE__/' . $model->getKey());
        $res->assertNoContent();
        $this->assertSoftDeleted('__TABLE__', ['id' => $model->getKey()]);
    }
}
PHP;
        $featureTest = str_replace(
            ['__DOMAIN__', '__NS_MODEL__', '__MODEL__', '__ROUTE_BASE__', '__TABLE__'],
            [$domain, $nsModel, $modelClass, $routeBase, (isset($tableDef['schema']) && $tableDef['schema']) ? ($tableDef['schema'] . '.' . $table) : $table],
            $featureTestTemplate
        );
        File::put($featureTestPath, $featureTest);
        $this->info('Feature test (Controller) criado: ' . $featureTestPath);
    }
}
