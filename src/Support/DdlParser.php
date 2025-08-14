<?php

namespace AlysonTrizotto\DdlCrud\Support;

class DdlParser
{
    /**
     * Parse CREATE TABLE statements from raw DDL.
     * Returns an array of table defs with keys: schema, table, full, columns, constraints, indexes.
     * @return array<int,array{
     *   schema: string|null,
     *   table: string,
     *   full: string,
     *   columns: array<int,array{name:string,type:string,raw:string}>,
     *   constraints: array<int,string>,
     *   indexes: array<int,array{name:string,columns:array<int,string>,unique:bool}>
     * }>
     */
    public function parseCreateTables(string $ddl): array
    {
        $results = [];
        $blocks = $this->parseCreateTableBlocks($ddl);

        foreach ($blocks as $b) {
            $fullName = $b['name'];
            $body = $b['body'];

            $columns = [];
            $constraints = [];

            foreach ($this->smartSplit($body) as $line) {
                $line = trim($line);
                if ($line === '') { continue; }
                $parsed = $this->parseColumnLine($line);
                if (!$parsed) { continue; }
                if ($parsed['kind'] === 'constraint') {
                    $constraints[] = $parsed['raw'];
                } elseif ($parsed['kind'] === 'column') {
                    $columns[] = [
                        'name' => $parsed['name'],
                        'type' => $parsed['type'],
                        'raw'  => $parsed['raw'],
                    ];
                }
            }

            $schema = null; $table = $fullName;
            if (str_contains($fullName, '.')) {
                [$schema, $table] = explode('.', $fullName, 2);
            }

            $results[] = [
                'schema' => $schema,
                'table' => $table,
                'full' => $fullName,
                'columns' => $columns,
                'constraints' => $constraints,
                'indexes' => [],
            ];
        }

        // Attach standalone indexes
        $indexMap = $this->parseCreateIndexes($ddl); // tableName => [indexes]
        foreach ($results as &$r) {
            $tbl = $r['full'];
            $short = $r['table'];
            if (isset($indexMap[$tbl])) {
                $r['indexes'] = array_merge($r['indexes'], $indexMap[$tbl]);
            } elseif (isset($indexMap[$short])) {
                $r['indexes'] = array_merge($r['indexes'], $indexMap[$short]);
            }
        }
        unset($r);

        return $results;
    }

    /**
     * Return all CREATE TABLE blocks with their names and inner bodies.
     * @return array<int,array{name:string,body:string}>
     */
    public function parseCreateTableBlocks(string $ddl): array
    {
        $out = [];
        $pattern = '/CREATE\s+TABLE\s+([\w\."]+)\s*\((.*?)\);/ims';
        if (preg_match_all($pattern, $ddl, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $out[] = [
                    'name' => trim(str_replace('"','', $m[1])),
                    'body' => trim($m[2]),
                ];
            }
        }
        return $out;
    }

    /**
     * Parse a single column/constraint line from a CREATE TABLE body.
     * @return array{kind:string,raw:string,name?:string,type?:string}|null
     */
    public function parseColumnLine(string $line): ?array
    {
        $lower = strtolower($line);
        if (str_starts_with($lower, 'constraint ') || str_starts_with($lower, 'foreign key') ||
            str_starts_with($lower, 'primary key') || str_starts_with($lower, 'unique ')) {
            return ['kind' => 'constraint', 'raw' => $line];
        }
        if (preg_match('/^\"?(\w+)\"?\s+([\w\[\]\(\), ]+)(.*)$/i', $line, $cm)) {
            return [
                'kind' => 'column',
                'name' => $cm[1],
                'type' => trim($cm[2]),
                'raw'  => $line,
            ];
        }
        return null;
    }

    /**
     * Parse standalone CREATE INDEX statements into a map keyed by table name.
     * @return array<string,array<int,array{name:string,columns:array<int,string>,unique:bool}>>
     */
    public function parseCreateIndexes(string $ddl): array
    {
        $map = [];
        $idxPattern = '/CREATE\s+(UNIQUE\s+)?INDEX\s+([\w_]+)\s+ON\s+([\w\."]+)\s*\(([^\)]+)\);/ims';
        if (preg_match_all($idxPattern, $ddl, $ims, PREG_SET_ORDER)) {
            foreach ($ims as $im) {
                $unique = trim($im[1]) !== '';
                $idxName = trim($im[2]);
                $tbl = trim(str_replace('"','', $im[3]));
                $cols = array_map(fn($s) => trim(str_replace('"','', $s)), explode(',', trim($im[4])));
                $map[$tbl] = $map[$tbl] ?? [];
                $map[$tbl][] = [
                    'name' => $idxName,
                    'columns' => $cols,
                    'unique' => $unique,
                ];
            }
        }
        return $map;
    }

    /**
     * Split a comma-separated list respecting nested parenthesis (e.g., function args, composite types).
     */
    public function smartSplit(string $body): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];
            if ($ch === '(') { $depth++; }
            if ($ch === ')') { $depth--; }
            if ($ch === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $ch;
        }
        if (trim($current) !== '') $parts[] = $current;
        return $parts;
    }
}
