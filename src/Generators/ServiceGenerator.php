<?php

namespace AlysonTrizotto\DdlCrud\Generators;

use AlysonTrizotto\DdlCrud\Generators\Contracts\GeneratorInterface;
use AlysonTrizotto\DdlCrud\Support\TemplateRenderer;
use Illuminate\Support\Facades\File;

class ServiceGenerator implements GeneratorInterface
{
    public function __construct(private TemplateRenderer $renderer = new TemplateRenderer()) {}

    public function generate(string $domain, string $modelClass, array $context = []): string
    {
        $dir = app_path('Services/' . $domain);
        File::ensureDirectoryExists($dir);
        $path = $dir . '/' . $modelClass . 'Service.php';

        $nsModel = "App\\Models\\{$domain}\\{$modelClass}";

        $fallback = <<<'PHP'
<?php

namespace App\Services\{$domain};

use {$nsModel};

class {$modelClass}Service
{
    public function paginate(array $filters = [], int $perPage = 15)
    {
        return {$modelClass}::query()->filter($filters)->paginate($perPage);
    }

    public function create(array $data): {$modelClass}
    {
        return {$modelClass}::create($data);
    }

    public function update({$modelClass} $model, array $data): {$modelClass}
    {
        $model->update($data);
        return $model;
    }

    public function delete({$modelClass} $model): void
    {
        $model->delete();
    }
}
PHP;

        $template = $this->renderer->loadStub('service', $fallback);
        $content = str_replace(
            ['App\\Services\\{$domain}', '{$nsModel}', '{$modelClass}'],
            ['App\\Services\\' . $domain, $nsModel, $modelClass],
            $template
        );

        File::put($path, $content);
        return $path;
    }
}
