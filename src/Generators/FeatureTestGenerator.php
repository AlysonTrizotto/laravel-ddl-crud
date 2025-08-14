<?php

namespace AlysonTrizotto\DdlCrud\Generators;

use AlysonTrizotto\DdlCrud\Generators\Contracts\GeneratorInterface;
use AlysonTrizotto\DdlCrud\Support\TemplateRenderer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FeatureTestGenerator implements GeneratorInterface
{
    public function __construct(private TemplateRenderer $renderer = new TemplateRenderer()) {}

    public function generate(string $domain, string $modelClass, array $context = []): string
    {
        $table = $context['table'] ?? Str::snake(Str::pluralStudly($modelClass));
        $tableDef = $context['tableDef'] ?? [];
        $routeBase = Str::of($domain)->lower() . '/' . Str::kebab(Str::pluralStudly($modelClass));

        $dir = base_path('tests/Feature/' . $domain);
        File::ensureDirectoryExists($dir);
        $path = $dir . "/{$modelClass}ControllerTest.php";

        $nsModel = "App\\Models\\{$domain}\\{$modelClass}";
        $fallback = <<<'PHP'
<?php

namespace Tests\Feature\__DOMAIN__;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use __NS_MODEL__;
use Illuminate\Support\Str;

class __MODEL__ControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function test_index_returns_ok(): void
    {
        __MODEL__::factory()->count(2)->create();
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
        $payload = __MODEL__::factory()->make()->toArray();
        $res = $this->postJson('/api/__ROUTE_BASE__', $payload);
        $res->assertCreated();
        $expected = collect($payload)
            ->except(['created_at','updated_at','deleted_at'])
            ->map(function ($v) {
                if (is_bool($v)) return $v ? 1 : 0;
                if (is_array($v)) return json_encode($v);
                return $v;
            })->toArray();
        $this->assertDatabaseHas('__TABLE__', $expected);
    }

    public function test_show_returns_one(): void
    {
        $model = __MODEL__::factory()->create();
        $res = $this->getJson('/api/__ROUTE_BASE__/' . $model->getKey());
        $res->assertOk();
    }

    public function test_update_updates_record(): void
    {
        $model = __MODEL__::factory()->create();
        $changes = __MODEL__::factory()->make()->toArray();
        $res = $this->putJson('/api/__ROUTE_BASE__/' . $model->getKey(), $changes);
        $res->assertOk();
        $expected = collect($changes)
            ->except(['created_at','updated_at','deleted_at'])
            ->map(function ($v) {
                if (is_bool($v)) return $v ? 1 : 0;
                if (is_array($v)) return json_encode($v);
                return $v;
            })->toArray();
        $this->assertDatabaseHas('__TABLE__', $expected);
    }

    public function test_destroy_deletes_record(): void
    {
        $model = __MODEL__::factory()->create();
        $res = $this->deleteJson('/api/__ROUTE_BASE__/' . $model->getKey());
        $res->assertNoContent();
        $this->assertSoftDeleted('__TABLE__', ['id' => $model->getKey()]);
    }
}
PHP;

        $template = $this->renderer->loadStub('feature.controller.test', $fallback);
        $content = str_replace(
            ['__DOMAIN__', '__NS_MODEL__', '__MODEL__', '__ROUTE_BASE__', '__TABLE__'],
            [
                $domain,
                $nsModel,
                $modelClass,
                $routeBase,
                (isset($tableDef['schema']) && $tableDef['schema']) ? ($tableDef['schema'] . '.' . $table) : $table
            ],
            $template
        );

        File::put($path, $content);
        return $path;
    }
}
