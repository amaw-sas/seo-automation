<?php

namespace App\Console\Commands\Seo;

use App\Models\Domain;
use App\Models\ReferringDomain;
use Illuminate\Console\Command;
use League\Csv\Reader;

class ImportReferringDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:referring-domains
                            {--domain= : Specific domain to import (optional)}
                            {--type= : Type: own, competitors, or all (default: all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar referring domains desde archivos referring-domains.csv';

    private array $spamPatterns;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainFilter = $this->option('domain');
        $type = $this->option('type') ?: 'all';

        $this->info('Importando referring domains desde archivos CSV');
        $this->newLine();

        $baseDir = config('seo.semrush_data_dir');
        $this->spamPatterns = config('seo.backlink_quality.spam_patterns', []);

        // Obtener dominios a procesar
        $domainsQuery = Domain::query();

        if ($domainFilter) {
            $domainsQuery->where('domain', $domainFilter);
        } elseif ($type === 'own') {
            $domainsQuery->where('is_own', true);
        } elseif ($type === 'competitors') {
            $domainsQuery->where('is_own', false);
        }

        $domains = $domainsQuery->get();

        if ($domains->isEmpty()) {
            $this->error("No se encontraron dominios para procesar");
            return Command::FAILURE;
        }

        $totalReferringDomains = 0;
        $totalUpdated = 0;
        $totalCreated = 0;

        foreach ($domains as $domain) {
            // Buscar archivo referring-domains.csv
            $domainSlug = str_replace(['.com.co', '.com', '.co'], '', $domain->domain);
            $possiblePaths = [
                $baseDir . '/mis-dominios/' . $domainSlug . '/referring-domains.csv',
                $baseDir . '/competidores/' . $domainSlug . '/referring-domains.csv',
            ];

            $csvFile = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $csvFile = $path;
                    break;
                }
            }

            if (!$csvFile) {
                $this->warn("No se encontró referring-domains.csv para {$domain->domain}");
                continue;
            }

            $this->line("Procesando: {$domain->domain}");

            try {
                $csv = Reader::createFromPath($csvFile, 'r');
                $csv->setHeaderOffset(0);

                $records = iterator_to_array($csv->getRecords());
                $imported = 0;
                $updated = 0;
                $created = 0;

                foreach ($records as $record) {
                    try {
                        $referringDomainName = $this->cleanDomain($record['Dominio'] ?? '');

                        if (empty($referringDomainName)) {
                            continue;
                        }

                        // Detectar spam
                        $isSpam = $this->detectSpam($referringDomainName);

                        // Buscar si existe
                        $referringDomain = ReferringDomain::where('domain', $referringDomainName)->first();

                        $data = [
                            'authority_score' => (int)($record['AS'] ?? 0),
                            'category' => $this->cleanCategory($record['Categoria'] ?? null),
                            'total_backlinks' => (int)($record['Backlinks'] ?? 0),
                            'is_spam' => $isSpam,
                        ];

                        if ($referringDomain) {
                            // Actualizar solo si hay cambios
                            if ($this->hasChanges($referringDomain, $data)) {
                                $referringDomain->update($data);
                                $updated++;
                            }
                        } else {
                            // Crear nuevo
                            ReferringDomain::create(array_merge(['domain' => $referringDomainName], $data));
                            $created++;
                        }

                        $imported++;

                    } catch (\Exception $e) {
                        if (config('seo.debug')) {
                            $this->warn("  Error en referring domain: " . $e->getMessage());
                        }
                    }
                }

                $this->info("  ✓ Procesados: {$imported} (Creados: {$created}, Actualizados: {$updated})");
                $totalReferringDomains += $imported;
                $totalCreated += $created;
                $totalUpdated += $updated;

            } catch (\Exception $e) {
                $this->error("  Error procesando {$domain->domain}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("RESUMEN:");
        $this->table(
            ['Métrica', 'Total'],
            [
                ['Referring Domains Procesados', $totalReferringDomains],
                ['Nuevos Creados', $totalCreated],
                ['Actualizados', $totalUpdated],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Clean domain name (remove protocol, www, etc.)
     */
    private function cleanDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^(https?://)?#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = rtrim($domain, '/');

        return $domain;
    }

    /**
     * Clean category name
     */
    private function cleanCategory(?string $category): ?string
    {
        if (empty($category)) {
            return null;
        }

        return trim($category);
    }

    /**
     * Detect spam using patterns from config
     */
    private function detectSpam(string $domain): bool
    {
        foreach ($this->spamPatterns as $pattern) {
            if (str_contains($domain, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if data has changes compared to existing model
     */
    private function hasChanges(ReferringDomain $model, array $data): bool
    {
        foreach ($data as $key => $value) {
            if ($model->{$key} !== $value) {
                return true;
            }
        }

        return false;
    }
}
