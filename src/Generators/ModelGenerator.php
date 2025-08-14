<?php

namespace AlysonTrizotto\DdlCrud\Generators;

use AlysonTrizotto\DdlCrud\Generators\Contracts\GeneratorInterface;
use AlysonTrizotto\DdlCrud\Support\TemplateRenderer;
use Illuminate\Support\Facades\File;

class ModelGenerator implements GeneratorInterface
{
    public function __construct(private TemplateRenderer $renderer = new TemplateRenderer()) {}

    public function generate(string $domain, string $modelClass, array $context = []): string
    {
        $tableDef = $context['tableDef'] ?? ['columns' => []];
        $fullTableName = $context['fullTableName'] ?? '';

        $dir = app_path('Models/' . $domain);
        File::ensureDirectoryExists($dir);
        $path = $dir . '/' . $modelClass . '.php';

        $fillable = [];
        foreach ($tableDef['columns'] as $col) {
            $name = $col['name'];
            if (in_array($name, ['id','created_at','updated_at','deleted_at'])) continue;
            $fillable[] = "'{$name}'";
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

        $primaryKey = $this->detectPrimaryKey($tableDef) ?? 'id';
        $isUuidPk = false;
        foreach ($tableDef['columns'] as $col) {
            if ($col['name'] === $primaryKey && str_starts_with(strtolower($col['type']), 'uuid')) {
                $isUuidPk = true; break;
            }
        }

        $replacements = [
            '__NAMESPACE__' => 'App\\Models\\' . $domain,
            '__MODEL__' => $modelClass,
            '__TABLE_PROP__' => 'protected $table = \'' . $fullTableName . '\';',
            '__PK_PROP__' => 'protected $primaryKey = \'' . $primaryKey . '\';',
            '__INC_PROP__' => $isUuidPk ? 'public $incrementing = false;' : 'public $incrementing = true;',
            '__KEYTYPE_PROP__' => $isUuidPk ? "protected \$keyType = 'string';" : "protected \$keyType = 'int';",
            '__FILLABLE__' => implode(",\n        ", $fillable),
            '__CASTS__' => implode(",\n        ", $casts),
            '__IMPORT_SOFT_DELETES__' => $this->usesSoftDeletes($tableDef) ? "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;" : '',
            '__TRAIT_SOFT_DELETES__' => $this->usesSoftDeletes($tableDef) ? ', SoftDeletes' : '',
        ];

        $fallback = <<<'PHP'
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

        $template = $this->renderer->loadStub('model', $fallback);
        $content = str_replace(array_keys($replacements), array_values($replacements), str_replace('__TRAIT_SOFT_DELETES__', $replacements['__TRAIT_SOFT_DELETES__'], $template));

        File::put($path, $content);
        return $path;
    }
    
    private function detectPrimaryKey(array $tableDef): ?string
    {
        foreach ($tableDef['columns'] as $col) {
            if (str_contains(strtolower($col['raw'] ?? ''), 'primary key')) {
                return $col['name'];
            }
        }
        return null;
    }

    private function usesSoftDeletes(array $tableDef): bool
    {
        foreach ($tableDef['columns'] as $col) {
            if (($col['name'] ?? '') === 'deleted_at') return true;
        }
        return false;
    }
}
