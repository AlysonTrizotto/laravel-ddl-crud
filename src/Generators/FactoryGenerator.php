<?php

namespace AlysonTrizotto\DdlCrud\Generators;

use AlysonTrizotto\DdlCrud\Generators\Contracts\GeneratorInterface;
use AlysonTrizotto\DdlCrud\Support\TemplateRenderer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FactoryGenerator implements GeneratorInterface
{
    public function __construct(private TemplateRenderer $renderer = new TemplateRenderer()) {}

    public function generate(string $domain, string $modelClass, array $context = []): string
    {
        $tableDef = $context['tableDef'] ?? ['columns' => []];

        $dir = base_path('database/factories/' . $domain);
        File::ensureDirectoryExists($dir);
        $path = $dir . '/' . $modelClass . 'Factory.php';

        $nsModel = "App\\Models\\{$domain}\\{$modelClass}";

        $attrs = [];
        foreach ($tableDef['columns'] as $col) {
            $name = $col['name'];
            $type = strtolower($col['type']);
            if (in_array($name, ['created_at','updated_at','deleted_at'])) continue;
            if ($name === 'id' && str_starts_with($type, 'uuid')) {
                $attrs[] = "'id' => (string) \\Illuminate\\Support\\Str::uuid()";
                continue;
            }
            if ($name === 'id') continue; // skip auto-increment PK
            if ($name !== 'id' && str_ends_with($name, '_id')) {
                $relatedModel = Str::studly(Str::singular(substr($name, 0, -3)));
                $relatedNs = "App\\Models\\{$domain}\\{$relatedModel}";
                // Check if related model class file exists; if not, fallback to numeric id
                $relatedPath = base_path('app/Models/' . $domain . '/' . $relatedModel . '.php');
                if (File::exists($relatedPath)) {
                    $attrs[] = "'{$name}' => \\{$relatedNs}::factory()";
                } else {
                    $attrs[] = "'{$name}' => fake()->numberBetween(1, 100000)";
                }
                continue;
            }
            if (str_starts_with($type, 'uuid')) {
                $attrs[] = "'{$name}' => (string) \\Illuminate\\Support\\Str::uuid()";
            } elseif (preg_match('/varchar\\((\\d+)\\)/', $type, $m)) {
                $len = (int)$m[1];
                $attrs[] = "'{$name}' => fake()->text(" . min($len, 50) . ")";
            } elseif (str_starts_with($type, 'text')) {
                $attrs[] = "'{$name}' => fake()->sentence()";
            } elseif (preg_match('/^decimal\(\d+,\d+\)/', $type) || str_starts_with($type, 'double') || str_starts_with($type, 'float')) {
                $attrs[] = "'{$name}' => fake()->randomFloat(2, 1, 1000)";
            } elseif (str_starts_with($type, 'json') || str_starts_with($type, 'jsonb')) {
                $attrs[] = "'{$name}' => []";
            } elseif (str_starts_with($type, 'boolean')) {
                $attrs[] = "'{$name}' => fake()->boolean()";
            } elseif (str_starts_with($type, 'bigint') || str_starts_with($type, 'int')) {
                $attrs[] = "'{$name}' => fake()->numberBetween(1, 1000)";
            } elseif ($type === 'date' || str_starts_with($type, 'date ')) {
                $attrs[] = "'{$name}' => fake()->date('Y-m-d')";
            } elseif (str_starts_with($type, 'timestamptz') || str_starts_with($type, 'timestamp') || $type === 'datetime' || str_starts_with($type, 'datetime')) {
                $attrs[] = "'{$name}' => now()->format('Y-m-d H:i:s')";
            } else {
                $attrs[] = "'{$name}' => fake()->word()";
            }
        }
        $attributes = implode(",\n            ", $attrs);

        $fallback = <<<'PHP'
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
    protected $model = \__NS_MODEL__::class;

    public function definition(): array
    {
        return [
            __ATTRS__
        ];
    }
}
PHP;

        $template = $this->renderer->loadStub('factory', $fallback);
        $content = str_replace(
            ['__DOMAIN__', '__NS_MODEL__', '__MODEL__', '__ATTRS__'],
            [$domain, $nsModel, $modelClass, $attributes],
            $template
        );

        File::put($path, $content);
        return $path;
    }
}
