<?php

namespace AlysonTrizotto\DdlCrud\Generators;

use AlysonTrizotto\DdlCrud\Generators\Contracts\GeneratorInterface;
use AlysonTrizotto\DdlCrud\Support\TemplateRenderer;
use Illuminate\Support\Facades\File;

class ResourceGenerator implements GeneratorInterface
{
    public function __construct(private TemplateRenderer $renderer = new TemplateRenderer()) {}

    public function generate(string $domain, string $modelClass, array $context = []): string
    {
        $dir = app_path('Http/Resources/' . $domain);
        File::ensureDirectoryExists($dir);
        $path = $dir . '/' . $modelClass . 'Resource.php';

        $fallback = <<<'PHP'
<?php

namespace App\Http\Resources\__DOMAIN__;

use Illuminate\Http\Resources\Json\JsonResource;

class __MODEL__Resource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
PHP;

        $template = $this->renderer->loadStub('resource', $fallback);
        $content = str_replace(
            ['__DOMAIN__', '__MODEL__'],
            [$domain, $modelClass],
            $template
        );

        File::put($path, $content);
        return $path;
    }
}
