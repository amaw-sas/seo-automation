<?php

namespace App\Console\Commands\Seo;

use App\Models\BacklinkOpportunity;
use App\Models\Domain;
use App\Models\ReferringDomain;
use Carbon\Carbon;
use Illuminate\Console\Command;
use League\Csv\Reader;

class ImportBacklinkOpportunities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:backlink-opportunities
                            {--identified-date= : Identified date (YYYY-MM-DD), defaults to 2026-01-25}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar backlink opportunities desde archivos CSV';

    private array $spamPatterns;
    private array $ownDomainSlugs = ['alquilatucarro', 'alquilame', 'alquicarros'];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $identifiedDate = $this->option('identified-date') ?: '2026-01-25';

        try {
            Carbon::parse($identifiedDate);
        } catch (\Exception $e) {
            $this->error("Fecha inválida: {$identifiedDate}");
            return Command::FAILURE;
        }

        $this->info('Importando backlink opportunities desde archivos CSV');
        $this->newLine();

        $this->spamPatterns = config('seo.backlink_quality.spam_patterns', []);
        $baseDir = config('seo.semrush_data_dir');

        // Find all backlink-gap CSV files
        $csvFiles = glob($baseDir . '/backlinks/backlink-gap-*.csv');

        if (empty($csvFiles)) {
            $this->error("No se encontraron archivos backlink-gap-*.csv");
            return Command::FAILURE;
        }

        $totalOpportunities = 0;
        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($csvFiles as $csvFile) {
            $filename = basename($csvFile);
            $this->line("Procesando: {$filename}");

            try {
                $csv = Reader::createFromPath($csvFile, 'r');
                $csv->setHeaderOffset(0);

                $records = iterator_to_array($csv->getRecords());
                $imported = 0;
                $created = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    try {
                        $referringDomainName = $this->cleanDomain($record['Dominio'] ?? '');

                        if (empty($referringDomainName)) {
                            $skipped++;
                            continue;
                        }

                        // Detectar spam
                        if ($this->detectSpam($referringDomainName)) {
                            $skipped++;
                            continue;
                        }

                        $as = (int)($record['AS'] ?? 0);
                        $category = $record['Categoria'] ?? null;

                        // Create or get referring domain
                        $referringDomain = ReferringDomain::firstOrCreate(
                            ['domain' => $referringDomainName],
                            [
                                'authority_score' => $as,
                                'category' => $category,
                                'is_spam' => false,
                            ]
                        );

                        // Check each own domain column
                        foreach ($this->ownDomainSlugs as $domainSlug) {
                            // Find column name (could be full domain or just slug)
                            $columnName = null;
                            foreach (array_keys($record) as $key) {
                                if (str_contains(strtolower($key), $domainSlug)) {
                                    $columnName = $key;
                                    break;
                                }
                            }

                            if (!$columnName) {
                                continue;
                            }

                            $backlinksCount = (int)($record[$columnName] ?? 0);

                            // If 0 backlinks, it's an opportunity
                            if ($backlinksCount === 0) {
                                // Find our domain
                                $ourDomain = Domain::where('domain', 'LIKE', '%' . $domainSlug . '%')
                                    ->where('is_own', true)
                                    ->first();

                                if (!$ourDomain) {
                                    continue;
                                }

                                // Find competitor with most backlinks from this referring domain
                                $competitorDomainId = $this->findCompetitorWithMostBacklinks($record);

                                if (!$competitorDomainId) {
                                    continue;
                                }

                                // Calculate priority
                                $priority = $this->calculatePriority($as, $record);

                                // Determine opportunity type
                                $opportunityType = $as >= 50 ? 'high_authority' : 'missing';

                                // Create opportunity
                                $existing = BacklinkOpportunity::where('referring_domain_id', $referringDomain->id)
                                    ->where('our_domain_id', $ourDomain->id)
                                    ->first();

                                if (!$existing) {
                                    BacklinkOpportunity::create([
                                        'referring_domain_id' => $referringDomain->id,
                                        'competitor_domain_id' => $competitorDomainId,
                                        'our_domain_id' => $ourDomain->id,
                                        'opportunity_type' => $opportunityType,
                                        'priority' => $priority,
                                        'status' => 'identified',
                                        'identified_at' => $identifiedDate,
                                    ]);

                                    $created++;
                                }

                                $imported++;
                            }
                        }

                    } catch (\Exception $e) {
                        $skipped++;
                        if (config('seo.debug')) {
                            $this->warn("  Error en row: " . $e->getMessage());
                        }
                    }
                }

                $this->info("  ✓ Procesadas: {$imported} oportunidades (Creadas: {$created}, Omitidas: {$skipped})");
                $totalOpportunities += $imported;
                $totalCreated += $created;
                $totalSkipped += $skipped;

            } catch (\Exception $e) {
                $this->error("  Error procesando {$filename}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("RESUMEN:");
        $this->table(
            ['Métrica', 'Total'],
            [
                ['Oportunidades Procesadas', $totalOpportunities],
                ['Nuevas Creadas', $totalCreated],
                ['Omitidas (spam/duplicadas)', $totalSkipped],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Clean domain name
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
     * Detect spam
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
     * Find competitor domain with most backlinks from this referring domain
     */
    private function findCompetitorWithMostBacklinks(array $record): ?int
    {
        $competitorSlugs = ['localiza', 'rentcars', 'equirent', 'kayak', 'hertz', 'alkilautos', 'despegar'];
        $maxBacklinks = 0;
        $competitorDomainId = null;

        foreach ($competitorSlugs as $slug) {
            // Find column name
            $columnName = null;
            foreach (array_keys($record) as $key) {
                if (str_contains(strtolower($key), $slug)) {
                    $columnName = $key;
                    break;
                }
            }

            if (!$columnName) {
                continue;
            }

            $backlinks = (int)($record[$columnName] ?? 0);

            if ($backlinks > $maxBacklinks) {
                $maxBacklinks = $backlinks;

                // Find domain ID
                $domain = Domain::where('domain', 'LIKE', '%' . $slug . '%')->first();
                if ($domain) {
                    $competitorDomainId = $domain->id;
                }
            }
        }

        return $competitorDomainId;
    }

    /**
     * Calculate priority based on AS and other factors
     */
    private function calculatePriority(int $as, array $record): string
    {
        // High priority: AS >= 50
        if ($as >= 50) {
            return 'high';
        }

        // Medium priority: AS >= 30
        if ($as >= 30) {
            return 'medium';
        }

        // Low priority: AS < 30
        return 'low';
    }
}
