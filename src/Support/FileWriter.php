<?php

namespace AlysonTrizotto\DdlCrud\Support;

use Illuminate\Support\Facades\File;

class FileWriter
{
    public static function ensureDirAndPut(string $path, string $content): void
    {
        $dir = dirname($path);
        File::ensureDirectoryExists($dir);
        File::put($path, $content);
    }
}
