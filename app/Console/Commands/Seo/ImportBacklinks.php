<?php

namespace App\Console\Commands\Seo;

use App\Models\Backlink;
use App\Models\Domain;
use App\Models\LinkType;
use App\Models\ReferringDomain;
use App\Services\Parsers\SpanishDateParser;
use Illuminate\Console\Command;
use League\Csv\Reader;

class ImportBacklinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:backlinks
                            {--domain= : Specific domain to import (optional)}
                            {--type= : Type: own, competitors, or all (default: all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar backlinks desde archivos backlinks.csv';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainFilter = $this->option('domain');
        $type = $this->option('type') ?: 'all';

        $this->info('Importando backlinks desde archivos CSV');
        $this->newLine();

        $baseDir = config('seo.semrush_data_dir');

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

        $totalBacklinks = 0;
        $totalSkipped = 0;
        $totalDomains = 0;
        $spamPatterns = config('seo.backlink_quality.spam_patterns', []);

        foreach ($domains as $domain) {
            // Buscar archivo backlinks.csv
            $domainSlug = str_replace(['.com.co', '.com', '.co'], '', $domain->domain);
            $possiblePaths = [
                $baseDir . '/mis-dominios/' . $domainSlug . '/backlinks.csv',
                $baseDir . '/competidores/' . $domainSlug . '/backlinks.csv',
            ];

            $csvFile = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $csvFile = $path;
                    break;
                }
            }

            if (!$csvFile) {
                $this->warn("No se encontró backlinks.csv para {$domain->domain}");
                continue;
            }

            $this->line("Procesando: {$domain->domain}");

            try {
                $csv = Reader::createFromPath($csvFile, 'r');
                $csv->setHeaderOffset(0);

                $records = iterator_to_array($csv->getRecords());
                $imported = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    try {
                        // Extraer dominio referente de Source URL
                        $sourceUrl = $record['Source URL'] ?? '';
                        $referringDomain = $this->extractDomain($sourceUrl);

                        if (empty($referringDomain)) {
                            $skipped++;
                            continue;
                        }

                        // Detectar spam
                        $isSpam = $this->detectSpam($referringDomain, $spamPatterns);

                        // Crear o obtener referring domain
                        $referringDomainModel = ReferringDomain::firstOrCreate(
                            ['domain' => $referringDomain],
                            [
                                'authority_score' => (int)($record['AS'] ?? 0),
                                'is_spam' => $isSpam,
                            ]
                        );

                        // Parsear fechas
                        $firstSeen = $this->parseDate($record['First Seen'] ?? null);
                        $lastSeen = $this->parseDate($record['Last Seen'] ?? null);

                        // Obtener link type
                        $linkTypeName = $record['Link Type'] ?? 'Text';
                        $linkTypeId = LinkType::where('name', $linkTypeName)
                            ->orWhere('slug', strtolower(str_replace(' ', '-', $linkTypeName)))
                            ->value('id');

                        // Crear backlink
                        Backlink::updateOrCreate(
                            [
                                'referring_domain_id' => $referringDomainModel->id,
                                'target_domain_id' => $domain->id,
                                'source_url' => $sourceUrl,
                            ],
                            [
                                'target_url' => $record['Target URL'] ?? '',
                                'anchor_text' => $record['Anchor Text'] ?? null,
                                'link_type_id' => $linkTypeId,
                                'first_seen_at' => $firstSeen,
                                'last_seen_at' => $lastSeen,
                                'is_active' => true,
                                'is_spam' => $isSpam,
                            ]
                        );

                        $imported++;

                    } catch (\Exception $e) {
                        $skipped++;
                        if (config('seo.debug')) {
                            $this->warn("  Error en backlink: " . $e->getMessage());
                        }
                    }
                }

                $this->info("  ✓ Importados: {$imported}, Omitidos: {$skipped}");
                $totalBacklinks += $imported;
                $totalSkipped += $skipped;
                $totalDomains++;

            } catch (\Exception $e) {
                $this->error("  Error procesando {$domain->domain}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("✓ Importación completada");
        $this->table(
            ['Métrica', 'Total'],
            [
                ['Backlinks importados', $totalBacklinks],
                ['Backlinks omitidos', $totalSkipped],
                ['Dominios procesados', $totalDomains],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Extract domain from URL
     */
    private function extractDomain(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // Add protocol if missing
        if (!preg_match('#^https?://#', $url)) {
            $url = 'http://' . $url;
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        // Remove www.
        $host = preg_replace('/^www\./', '', $host);

        return $host;
    }

    /**
     * Detect spam domain
     */
    private function detectSpam(string $domain, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (stripos($domain, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse Spanish date or relative date
     */
    private function parseDate(?string $dateStr): ?\Carbon\Carbon
    {
        if (empty($dateStr)) {
            return null;
        }

        // Handle relative dates (ej: "hace 20 h", "hace 7 d.")
        if (preg_match('/hace\s+(\d+)\s*(h|d|m|y)/', $dateStr, $matches)) {
            $amount = (int)$matches[1];
            $unit = $matches[2];

            $now = \Carbon\Carbon::now();

            return match($unit) {
                'h' => $now->subHours($amount),
                'd' => $now->subDays($amount),
                'm' => $now->subMonths($amount),
                'y' => $now->subYears($amount),
                default => $now,
            };
        }

        // Try Spanish date parser
        return SpanishDateParser::parse($dateStr);
    }
}
