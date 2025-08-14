<?php

namespace AlysonTrizotto\DdlCrud\Generators;

use AlysonTrizotto\DdlCrud\Generators\Contracts\GeneratorInterface;
use AlysonTrizotto\DdlCrud\Support\TemplateRenderer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ControllerGenerator implements GeneratorInterface
{
    public function __construct(private TemplateRenderer $renderer = new TemplateRenderer()) {}

    public function generate(string $domain, string $modelClass, array $context = []): string
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
        $varModel = Str::camel($modelClass);

        $fallback = <<<'PHP'
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
    public function __construct(private {$modelClass}Service $service) {}

    public function index(Request $request)
    {
        $data = $this->service->paginate($request->all(), (int) $request->get('per_page', 15));
        // Wrap paginator in Resource so it handles pagination metadata automatically
        return new {$modelClass}Resource($data);
    }

    public function store(Store{$modelClass}Request $request)
    {
        $model = $this->service->create($request->validated());
        return (new {$modelClass}Resource($model))->response()->setStatusCode(201);
    }

    public function show({$modelClass} $__VAR_MODEL__)
    {
        return new {$modelClass}Resource($__VAR_MODEL__);
    }

    public function update({$modelClass} $__VAR_MODEL__, {$updateClass} $request)
    {
        $updated = $this->service->update($__VAR_MODEL__, $request->validated());
        return new {$modelClass}Resource($updated);
    }

    public function destroy({$modelClass} $__VAR_MODEL__)
    {
        $this->service->delete($__VAR_MODEL__);
        return response()->noContent();
    }
}
PHP;

        $template = $this->renderer->loadStub('controller.api', $fallback);
        $content = str_replace(
            [
                'App\\Http\\Controllers\\API\\{$domain}',
                '{$nsModel}', '{$nsService}', '{$nsStore}', '{$nsUpdate}', '{$modelClass}', '{$nsResource}',
                '{$storeClass}', '{$updateClass}', '__VAR_MODEL__'
            ],
            [
                'App\\Http\\Controllers\\API\\' . $domain,
                $nsModel, $nsService, $nsStore, $nsUpdate, $modelClass, $nsResource,
                $storeClass, $updateClass, $varModel
            ],
            $template
        );

        File::put($path, $content);
        return $path;
    }
}
