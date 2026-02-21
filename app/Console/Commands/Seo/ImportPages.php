<?php

namespace App\Console\Commands\Seo;

use App\Models\Domain;
use App\Models\DomainPage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:pages
                            {--domain= : Specific domain to import (optional)}
                            {--type= : Type: own, competitors, or all (default: all)}
                            {--snapshot-date= : Snapshot date (YYYY-MM-DD), defaults to 2026-01-25}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar top pages desde archivos XLSX';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainFilter = $this->option('domain');
        $type = $this->option('type') ?: 'all';
        $snapshotDate = $this->option('snapshot-date') ?: '2026-01-25';

        try {
            Carbon::parse($snapshotDate);
        } catch (\Exception $e) {
            $this->error("Fecha de snapshot inválida: {$snapshotDate}");
            return Command::FAILURE;
        }

        $this->info('Importando top pages desde archivos XLSX');
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

        $totalPages = 0;
        $totalCreated = 0;
        $totalUpdated = 0;

        foreach ($domains as $domain) {
            // Buscar archivo top-pages.xlsx
            $domainSlug = str_replace(['.com.co', '.com', '.co'], '', $domain->domain);
            $possiblePaths = [
                $baseDir . '/mis-dominios/' . $domainSlug . '/top-pages.xlsx',
                $baseDir . '/competidores/' . $domainSlug . '/top-pages.xlsx',
            ];

            $xlsxFile = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $xlsxFile = $path;
                    break;
                }
            }

            if (!$xlsxFile) {
                $this->warn("No se encontró top-pages.xlsx para {$domain->domain}");
                continue;
            }

            $this->line("Procesando: {$domain->domain}");

            try {
                $reader = IOFactory::createReader('Xlsx');
                $spreadsheet = $reader->load($xlsxFile);
                $sheet = $spreadsheet->getActiveSheet();

                $highestRow = $sheet->getHighestRow();
                $imported = 0;
                $created = 0;
                $updated = 0;

                // Headers en row 1: URL, Traffic (%), Number of Keywords, Traffic, ...
                for ($row = 2; $row <= $highestRow; $row++) {
                    try {
                        $url = $sheet->getCell('A' . $row)->getValue();

                        if (empty($url)) {
                            continue;
                        }

                        // Normalizar URL
                        $url = $this->normalizeUrl($url);

                        $traffic = (int)$sheet->getCell('D' . $row)->getValue(); // Traffic (columna D)
                        $keywordsCount = (int)$sheet->getCell('C' . $row)->getValue(); // Number of Keywords (columna C)

                        // Crear o actualizar
                        $existing = DomainPage::where('domain_id', $domain->id)
                            ->where('url', $url)
                            ->where('snapshot_date', $snapshotDate)
                            ->first();

                        $data = [
                            'domain_id' => $domain->id,
                            'url' => $url,
                            'traffic' => $traffic,
                            'keywords_count' => $keywordsCount,
                            'backlinks_count' => 0, // No disponible en este archivo
                            'snapshot_date' => $snapshotDate,
                        ];

                        if ($existing) {
                            $existing->update($data);
                            $updated++;
                        } else {
                            DomainPage::create($data);
                            $created++;
                        }

                        $imported++;

                    } catch (\Exception $e) {
                        if (config('seo.debug')) {
                            $this->warn("  Error en row {$row}: " . $e->getMessage());
                        }
                    }
                }

                $this->info("  ✓ Importadas: {$imported} páginas (Creadas: {$created}, Actualizadas: {$updated})");
                $totalPages += $imported;
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
                ['Páginas Importadas', $totalPages],
                ['Nuevas Creadas', $totalCreated],
                ['Actualizadas', $totalUpdated],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Normalize URL (remove protocol, ensure consistency)
     */
    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        // Ensure URL has protocol
        if (!preg_match('#^https?://#', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }
}
