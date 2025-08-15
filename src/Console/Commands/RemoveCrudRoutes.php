<?php

namespace AlysonTrizotto\DdlCrud\Console\Commands;

use Illuminate\Console\Command;
use AlysonTrizotto\DdlCrud\Generators\RoutesGenerator;
use Illuminate\Support\Str;

class RemoveCrudRoutes extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ddl-crud:routes:remove {domain : Domínio CamelCase, ex.: Checklist}';

    /**
     * The console command description.
     */
    protected $description = 'Remove com segurança o bloco de rotas do pacote para um domínio (entre marcadores BEGIN/END)';

    public function handle(): int
    {
        $domain = $this->argument('domain');
        if (!$domain || trim($domain) === '') {
            $this->error('Informe o domínio (CamelCase).');
            return self::FAILURE;
        }
        $domain = Str::studly(trim($domain));

        $path = (new RoutesGenerator())->removeDomainBlock($domain);
        $this->info("Bloco de rotas removido (se existia) para domínio '{$domain}' em: {$path}");
        return self::SUCCESS;
    }
}
