<?php

namespace AlysonTrizotto\DdlCrud\Generators;

use AlysonTrizotto\DdlCrud\Generators\Contracts\GeneratorInterface;
use AlysonTrizotto\DdlCrud\Support\TemplateRenderer;
use Illuminate\Support\Facades\File;

class UnitTestGenerator implements GeneratorInterface
{
    public function __construct(private TemplateRenderer $renderer = new TemplateRenderer()) {}

    public function generate(string $domain, string $modelClass, array $context = []): string
    {
        $table = $context['table'] ?? '';
        $tableDef = $context['tableDef'] ?? [];

        $dir = base_path('tests/Unit/' . $domain);
        File::ensureDirectoryExists($dir);

        $nsModel = "App\\Models\\{$domain}\\{$modelClass}";
        $nsService = "App\\Services\\{$domain}\\{$modelClass}Service";

        // Model test
        $modelTestPath = $dir . "/{$modelClass}ModelTest.php";
        $modelFallback = <<<'PHP'
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
        $modelTpl = $this->renderer->loadStub('unit.model.test', $modelFallback);
        $modelContent = str_replace(
            ['__DOMAIN__', '__NS_MODEL__', '__MODEL__'],
            [$domain, $nsModel, $modelClass],
            $modelTpl
        );
        File::put($modelTestPath, $modelContent);

        // Service test
        $serviceTestPath = $dir . "/{$modelClass}ServiceTest.php";
        $serviceFallback = <<<'PHP'
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
        $serviceTpl = $this->renderer->loadStub('unit.service.test', $serviceFallback);
        $serviceContent = str_replace(
            ['__DOMAIN__', '__NS_SERVICE__', '__NS_MODEL__', '__MODEL__', '__TABLE__'],
            [
                $domain,
                $nsService,
                $nsModel,
                $modelClass,
                (isset($tableDef['schema']) && $tableDef['schema']) ? ($tableDef['schema'] . '.' . $table) : $table
            ],
            $serviceTpl
        );
        File::put($serviceTestPath, $serviceContent);

        // Return last path per interface; both files created
        return $serviceTestPath;
    }
}
