<?php

namespace AlysonTrizotto\DdlCrud\Generators;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class RoutesGenerator
{
    /**
     * Append an apiResource route for the given model into routes/api.php
     * without overwriting existing routes. Uses BEGIN/END markers per domain
     * and ensures idempotency for repeated runs.
     */
    /**
     * @param string      $domain      CamelCased domain name
     * @param string      $modelClass  Studly model class name
     * @param string      $table       Table name from DDL (used to derive default slug)
     * @param string|null $prefix      Optional Route::prefix('...')
     * @param string|null $slugOverride Optional route slug override
     * @param array|null  $middlewares Optional list of middlewares
     * @param string|null $namePrefix  Optional Route::name('...')->group
     * @param array|null  $only        Methods to include (index,show,store,update,destroy)
     * @param array|null  $except      Methods to exclude
     */
    public function appendApiRoute(
        string $domain,
        string $modelClass,
        string $table,
        ?string $prefix = null,
        ?string $slugOverride = null,
        ?array $middlewares = null,
        ?string $namePrefix = null,
        ?array $only = null,
        ?array $except = null,
        ?string $nestedPath = null
    ): string
    {
        $routesPath = base_path('routes/api.php');

        // Ensure the file exists
        if (!File::exists($routesPath)) {
            File::ensureDirectoryExists(dirname($routesPath));
            File::put($routesPath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n");
        }

        $contents = File::get($routesPath);

        $controllerFqn = "\\App\\Http\\Controllers\\API\\{$domain}\\{$modelClass}Controller";

        // Prefer the table name as the route slug; fall back to plural kebab of model
        $slug = $nestedPath ?: ($slugOverride ?: Str::of($table))
            ->replace('\\\\', '/') // normalize
            ->replace(' ', '-')
            ->replace('_', '-')
            ->lower()
            ->value();
        if (empty($slug)) {
            $slug = Str::kebab(Str::pluralStudly($modelClass));
        }

        $routeExpr = "Route::apiResource('{$slug}', {$controllerFqn}::class)";
        if (!empty($only)) {
            $onlyList = "['".implode("','", array_map('trim', $only))."']";
            $routeExpr .= "->only({$onlyList})";
        }
        if (!empty($except)) {
            $exceptList = "['".implode("','", array_map('trim', $except))."']";
            $routeExpr .= "->except({$exceptList})";
        }
        $routeExpr .= ';';

        // Build group chain: first call uses ::, next use ->
        $methods = [];
        if ($prefix !== null && $prefix !== '') {
            $methods[] = "prefix('".addslashes($prefix)."')";
        }
        if (!empty($middlewares)) {
            $escaped = array_map(fn($m) => "'".addslashes(trim((string)$m))."'", $middlewares);
            $methods[] = 'middleware(['.implode(',', $escaped).'])';
        }
        if ($namePrefix !== null && $namePrefix !== '') {
            $methods[] = "name('".addslashes($namePrefix)."')";
        }

        $routeLine = $routeExpr;
        if (!empty($methods)) {
            $chain = '';
            foreach ($methods as $i => $m) {
                $chain .= ($i === 0 ? '::' : '->') . $m;
            }
            $routeLine = 'Route' . $chain . "->group(function () {\n        {$routeExpr}\n    });";
        }

        $begin = "// BEGIN: DDL-CRUD routes [{$domain}]";
        $end   = "// END: DDL-CRUD routes [{$domain}]";

        // If a route for this slug already exists anywhere, do nothing (idempotent)
        if (Str::contains($contents, "apiResource('{$slug}'")) {
            return $routesPath;
        }

        // If a domain block exists, insert before END marker
        if (Str::contains($contents, $begin) && Str::contains($contents, $end)) {
            $contents = preg_replace_callback(
                "#".preg_quote($begin, '#')."(.*?)".preg_quote($end, '#')."#s",
                function ($m) use ($begin, $end, $routeLine) {
                    $blockBody = rtrim($m[1]);
                    // Ensure the route isn't already in the domain block
                    if (!Str::contains($blockBody, $routeLine)) {
                        $blockBody .= (strlen($blockBody) ? "\n" : "")."    {$routeLine}\n";
                    }
                    return $begin."\n".$blockBody."\n".$end;
                },
                $contents,
                1
            );
        } else {
            // Append a new domain block at the end of the file
            $append = "\n\n{$begin}\n    {$routeLine}\n{$end}\n";
            // Guarantee file ends with a newline before appending
            if (!str_ends_with($contents, "\n")) {
                $contents .= "\n";
            }
            $contents .= $append;
        }

        File::put($routesPath, $contents);
        return $routesPath;
    }

    /**
     * Remove the routes block for a given domain, delimited by BEGIN/END markers.
     * Safe no-op if the block does not exist.
     */
    public function removeDomainBlock(string $domain): string
    {
        $routesPath = base_path('routes/api.php');
        if (!File::exists($routesPath)) {
            return $routesPath;
        }
        $contents = File::get($routesPath);
        $begin = "// BEGIN: DDL-CRUD routes [{$domain}]";
        $end   = "// END: DDL-CRUD routes [{$domain}]";
        if (Str::contains($contents, $begin) && Str::contains($contents, $end)) {
            $pattern = '#'.preg_quote($begin, '#').'(.*?)'.preg_quote($end, '#').'\n?#s';
            $contents = preg_replace($pattern, '', $contents, 1) ?? $contents;
            // Clean up multiple blank lines
            $contents = preg_replace("/\n{3,}/", "\n\n", $contents) ?? $contents;
            File::put($routesPath, $contents);
        }
        return $routesPath;
    }
}
