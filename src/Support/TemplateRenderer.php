<?php

namespace AlysonTrizotto\DdlCrud\Support;

use Illuminate\Support\Facades\File;

class TemplateRenderer
{
    /**
     * Try to load a project-level stub by name, with optional fallback contents.
     */
    public function loadStub(string $name, ?string $fallback = null): string
    {
        $paths = [
            base_path('stubs/cascade/' . $name . '.stub'),
            base_path('stubs/' . $name . '.stub'),
        ];
        foreach ($paths as $p) {
            if (File::exists($p)) {
                return File::get($p);
            }
        }
        return $fallback ?? '';
    }

    /**
     * Simple placeholder replacement.
     *
     * @param array<string,string> $replacements
     */
    public function render(string $template, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
