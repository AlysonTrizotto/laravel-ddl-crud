<?php

namespace AlysonTrizotto\DdlCrud\Generators;

use AlysonTrizotto\DdlCrud\Generators\Contracts\GeneratorInterface;
use AlysonTrizotto\DdlCrud\Support\TemplateRenderer;
use Illuminate\Support\Facades\File;

class RequestGenerator implements GeneratorInterface
{
    public function __construct(private TemplateRenderer $renderer = new TemplateRenderer()) {}

    public function generate(string $domain, string $modelClass, array $context = []): string
    {
        $tableDef = $context['tableDef'] ?? ['columns' => []];
        $dir = app_path('Http/Requests/' . $domain . '/' . $modelClass);
        File::ensureDirectoryExists($dir);

        $rulesCreate = $this->inferRules($tableDef, true);
        $rulesUpdate = $this->inferRules($tableDef, false);

        $storePath = $dir . '/Store' . $modelClass . 'Request.php';
        $updatePath = $dir . '/Update' . $modelClass . 'Request.php';

        $this->writeRequest($storePath, $domain, $modelClass, 'Store', $rulesCreate);
        $this->writeRequest($updatePath, $domain, $modelClass, 'Update', $rulesUpdate);

        // Return the last path (contract requires string); both are created.
        return $updatePath;
    }

    /**
     * @return array<string,string>
     */
    private function inferRules(array $tableDef, bool $isCreate): array
    {
        $rules = [];
        foreach ($tableDef['columns'] as $col) {
            $name = $col['name'];
            $type = strtolower($col['type']);
            $raw = strtolower($col['raw']);
            if (in_array($name, ['id','created_at','updated_at','deleted_at'])) continue;
            $colRules = [];
            $nullable = str_contains($raw, 'not null') ? false : true;
            // Create: required if not nullable; Update: nullable accepted
            $colRules[] = ($isCreate && !$nullable) ? 'required' : 'nullable';
            if ($name !== 'id' && str_ends_with($name, '_id')) {
                $colRules[] = 'integer';
            } elseif (str_starts_with($type, 'uuid')) {
                $colRules[] = 'uuid';
            } elseif (preg_match('/^decimal\(\d+,\d+\)/', $type) || str_starts_with($type, 'double') || str_starts_with($type, 'float')) {
                $colRules[] = 'numeric';
            } elseif (preg_match('/varchar\((\d+)\)/', $type, $m)) {
                $colRules[] = 'string|max:' . $m[1];
            } elseif (str_starts_with($type, 'text')) {
                $colRules[] = 'string';
            } elseif (str_starts_with($type, 'jsonb') || str_starts_with($type, 'json')) {
                // Accept JSON payloads as arrays (Laravel will handle serialization/casting later if configured)
                $colRules[] = 'array';
            } elseif (str_starts_with($type, 'bigint') || str_starts_with($type, 'int')) {
                $colRules[] = 'integer';
            } elseif (str_starts_with($type, 'boolean')) {
                $colRules[] = 'boolean';
            } elseif ($type === 'date' || str_starts_with($type, 'date ')) {
                $colRules[] = 'date';
            } elseif ($type === 'datetime' || str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
                $colRules[] = 'date';
            } else {
                $colRules[] = 'string';
            }
            $rules[$name] = implode('|', $colRules);
        }
        return $rules;
    }

    private function writeRequest(string $path, string $domain, string $modelClass, string $prefix, array $rules): void
    {
        $ns = "App\\Http\\Requests\\{$domain}\\{$modelClass}";
        $class = $prefix . $modelClass . 'Request';
        $rulesPretty = implode("\n", array_map(fn($k,$v)=>"            '".$k."' => '".$v."',", array_keys($rules), $rules));

        $fallback = <<<'PHP'
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
        $template = $this->renderer->loadStub('request', $fallback);
        $content = str_replace(['{$ns}', '{$class}', '{$rulesPretty}'], [$ns, $class, $rulesPretty], $template);
        File::put($path, $content);
    }
}
