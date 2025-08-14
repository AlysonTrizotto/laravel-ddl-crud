<?php

namespace AlysonTrizotto\DdlCrud\Generators\Contracts;

interface GeneratorInterface
{
    /**
     * Generate artifact(s) and return primary written path.
     */
    public function generate(string $domain, string $modelClass, array $context = []): string;
}
