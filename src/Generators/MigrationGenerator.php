<?php

namespace AlysonTrizotto\DdlCrud\Generators;

use AlysonTrizotto\DdlCrud\Generators\Contracts\GeneratorInterface;
use AlysonTrizotto\DdlCrud\Support\FileWriter;
use Illuminate\Support\Facades\File;

class MigrationGenerator implements GeneratorInterface
{
    public function generate(string $domain, string $modelClass, array $context = []): string
    {
        $schema = $context['schema'] ?? null;
        $table = $context['table'] ?? '';
        $tableDef = $context['tableDef'] ?? ['columns' => [], 'constraints' => [], 'indexes' => []];

        $timestamp = date('Y_m_d_His');
        $file = base_path('database/migrations/' . $timestamp . '_create_' . ($schema ? $schema . '_' : '') . $table . '_table.php');

        $lines = [];
        $lines[] = "<?php";
        $lines[] = "";
        $lines[] = "use Illuminate\\Database\\Migrations\\Migration;";
        $lines[] = "use Illuminate\\Database\\Schema\\Blueprint;";
        $lines[] = "use Illuminate\\Support\\Facades\\Schema;";
        $lines[] = "use Illuminate\\Support\\Facades\\DB;";
        $lines[] = "";
        $lines[] = "return new class extends Migration {";
        $lines[] = "    public function up(): void";
        $lines[] = "    {";
        if ($schema) {
            $lines[] = "        DB::statement(\"CREATE SCHEMA IF NOT EXISTS {$schema}\");";
        }
        $tableNameLiteral = $schema ? "$schema.$table" : $table;
        $lines[] = "        if (Schema::hasTable('{$tableNameLiteral}')) { return; }";
        $lines[] = "        Schema::create('{$tableNameLiteral}', function (Blueprint \$table) {";

        foreach ($tableDef['columns'] as $col) {
            $lines[] = '            ' . $this->columnToBlueprint($col);
        }

        foreach ($tableDef['constraints'] as $constraint) {
            $c = strtolower(trim($constraint));
            if (preg_match('/foreign\s+key\s*\(([^\)]+)\)\s*references\s+([\w\.]+)\s*\(([^\)]+)\)/i', $c, $m)) {
                $cols = array_map('trim', explode(',', $m[1]));
                $refTable = trim($m[2]);
                $refCols = array_map('trim', explode(',', $m[3]));
                if (count($cols) === 1 && count($refCols) === 1) {
                    $col = $cols[0];
                    $refCol = $refCols[0];
                    $lines[] = '            $table->foreign(\'' . $col . '\')->references(\'' . $refCol . '\')->on(\'' . $refTable . '\');';
                }
            }
        }

        if (!empty($tableDef['indexes'])) {
            foreach ($tableDef['indexes'] as $idx) {
                $colsList = "['" . implode("','", $idx['columns']) . "']";
                if (!empty($idx['unique'])) {
                    $lines[] = '            $table->unique(' . $colsList . ", '{$idx['name']}');";
                } else {
                    $lines[] = '            $table->index(' . $colsList . ", '{$idx['name']}');";
                }
            }
        }

        $lines[] = "        });";
        $lines[] = "    }";
        $lines[] = "";
        $lines[] = "    public function down(): void";
        $lines[] = "    {";
        $lines[] = "        Schema::disableForeignKeyConstraints();";
        $lines[] = "        Schema::dropIfExists('{$tableNameLiteral}');";
        $lines[] = "        Schema::enableForeignKeyConstraints();";
        $lines[] = "    }";
        $lines[] = "};";

        FileWriter::ensureDirAndPut($file, implode(PHP_EOL, $lines));
        return $file;
    }

    private function columnToBlueprint(array $col): string
    {
        $name = $col['name'];
        $type = strtolower($col['type']);
        $raw = strtolower($col['raw']);

        $nullable = !str_contains($raw, ' not null');

        $default = null;
        if (preg_match('/default\s+([^\s,]+)/', $raw, $dm)) {
            $default = trim($dm[1]);
        }

        $mapped = $this->mapSqlToBaseBlueprint($name, $type);
        if ($mapped['terminal']) {
            return $mapped['base'] . ';';
        }
        $code = $mapped['base'];

        if (str_contains($raw, 'primary key')) {
            $code .= "->primary()";
        }

        if ($default) {
            if ($default === 'now()') {
                $code .= "->useCurrent()";
            } elseif (preg_match('/^current_timestamp(\(\))?$/i', $default)) {
                $code .= "->useCurrent()";
            } elseif ($default === "gen_random_uuid()") {
                $code .= "->default(DB::raw('gen_random_uuid()'))";
            } elseif (preg_match("/^'(.*)'$/", $default)) {
                $code .= "->default({$default})";
            } elseif (strtolower($default) === 'null') {
                // skip
            } else {
                $code .= "->default({$default})";
            }
        }

        if ($name === 'deleted_at') {
            return '$table->softDeletes();';
        }

        if (str_contains($raw, ' unique') && !str_contains($raw, 'primary key')) {
            $code .= "->unique()";
        }

        if ($nullable) {
            $code .= "->nullable()";
        }

        return $code . ';';
    }

    private function mapSqlToBaseBlueprint(string $name, string $type): array
    {
        $t = strtolower($type);
        if (str_starts_with($t, 'serial') || str_starts_with($t, 'bigserial')) {
            return ['base' => '$table->id(' . "'" . $name . "'" . ')', 'terminal' => true];
        }
        if (str_starts_with($t, 'uuid')) {
            return ['base' => '$table->uuid(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if (preg_match('/^char\((\d+)\)/', $t, $cm)) {
            if ($cm[1] == '36' && str_contains($name, 'uuid')) {
                return ['base' => '$table->uuid(' . "'" . $name . "'" . ')', 'terminal' => false];
            }
            return ['base' => '$table->string(' . "'" . $name . "'" . ', ' . $cm[1] . ')', 'terminal' => false];
        }
        if (preg_match('/^decimal\((\d+),(\d+)\)/', $t, $dm)) {
            return ['base' => '$table->decimal(' . "'" . $name . "'" . ', ' . $dm[1] . ', ' . $dm[2] . ')', 'terminal' => false];
        }
        if (str_starts_with($t, 'double')) {
            return ['base' => '$table->double(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if (str_starts_with($t, 'float')) {
            return ['base' => '$table->float(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if ($t === 'date' || str_starts_with($t, 'date ')) {
            return ['base' => '$table->date(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if ($t === 'datetime' || str_starts_with($t, 'datetime')) {
            return ['base' => '$table->dateTime(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if (str_starts_with($t, 'timestamptz')) {
            return ['base' => '$table->timestampTz(' . "'" . $name . "'" . ', 6)', 'terminal' => false];
        }
        if (str_starts_with($t, 'timestamp')) {
            return ['base' => '$table->timestamp(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if (str_starts_with($t, 'jsonb') || str_starts_with($t, 'json')) {
            return ['base' => '$table->json(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if (str_starts_with($t, 'text')) {
            return ['base' => '$table->text(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if (preg_match('/varchar\((\d+)\)/', $t, $tm)) {
            return ['base' => '$table->string(' . "'" . $name . "'" . ', ' . $tm[1] . ')', 'terminal' => false];
        }
        if (str_starts_with($t, 'bigint')) {
            return ['base' => '$table->bigInteger(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if ($t === 'int4' || $t === 'integer' || str_starts_with($t, 'int')) {
            return ['base' => '$table->integer(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if (str_starts_with($t, 'boolean')) {
            return ['base' => '$table->boolean(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        if (str_starts_with($t, 'uuid[')) {
            return ['base' => '$table->json(' . "'" . $name . "'" . ')', 'terminal' => false];
        }
        return ['base' => '$table->string(' . "'" . $name . "'" . ')', 'terminal' => false];
    }
}
