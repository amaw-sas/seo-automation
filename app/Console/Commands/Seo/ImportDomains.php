<?php

namespace App\Console\Commands\Seo;

use App\Models\Domain;
use App\Models\DomainType;
use Illuminate\Console\Command;

class ImportDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:domains';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar dominios propios y competidores desde configuración';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Importando dominios...');

        $ownDomains = config('seo.own_domains', []);
        $competitorDomains = config('seo.competitor_domains', []);

        $ownTypeId = DomainType::where('slug', 'own')->value('id');
        $competitorTypeId = DomainType::where('slug', 'competitor_local')->value('id');

        $imported = 0;

        // Importar dominios propios
        foreach ($ownDomains as $domain) {
            Domain::updateOrCreate(
                ['domain' => $domain],
                [
                    'domain_type_id' => $ownTypeId,
                    'is_own' => true,
                    'is_active' => true,
                ]
            );
            $this->line("  ✓ {$domain} (propio)");
            $imported++;
        }

        // Importar competidores
        foreach ($competitorDomains as $domain) {
            Domain::updateOrCreate(
                ['domain' => $domain],
                [
                    'domain_type_id' => $competitorTypeId,
                    'is_own' => false,
                    'is_active' => true,
                ]
            );
            $this->line("  ✓ {$domain} (competidor)");
            $imported++;
        }

        $this->newLine();
        $this->info("✓ {$imported} dominios importados exitosamente.");

        return Command::SUCCESS;
    }
}
