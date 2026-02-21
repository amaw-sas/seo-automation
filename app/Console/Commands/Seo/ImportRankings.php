<?php

namespace App\Console\Commands\Seo;

use App\Models\Domain;
use App\Services\Importers\KeywordImporter;
use App\Services\Importers\RankingImporter;
use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportRankings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:rankings
                            {--domain= : Specific domain to import (optional)}
                            {--snapshot-date= : Snapshot date (YYYY-MM-DD, default: today)}
                            {--limit= : Limit number of rankings per domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar rankings desde archivos organic-keywords.xlsx';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainFilter = $this->option('domain');
        $snapshotDate = $this->option('snapshot-date') ?: now()->format('Y-m-d');
        $limit = $this->option('limit');

        $this->info('Importando rankings desde archivos organic-keywords.xlsx');
        $this->info("Snapshot date: {$snapshotDate}");
        $this->newLine();

        $baseDir = config('seo.semrush_data_dir');

        // Obtener dominios a procesar
        if ($domainFilter) {
            $domains = Domain::where('domain', $domainFilter)->get();
            if ($domains->isEmpty()) {
                $this->error("Dominio no encontrado: {$domainFilter}");
                return Command::FAILURE;
            }
        } else {
            $domains = Domain::all();
        }

        $rankingImporter = new RankingImporter();
        $keywordImporter = new KeywordImporter();

        $totalRankings = 0;
        $totalDomains = 0;

        foreach ($domains as $domain) {
            // Buscar archivo organic-keywords.xlsx o keywords.xlsx en diferentes ubicaciones
            $domainSlug = str_replace(['.com.co', '.com', '.co'], '', $domain->domain);
            $possiblePaths = [
                $baseDir . '/mis-dominios/' . $domainSlug . '/organic-keywords.xlsx',
                $baseDir . '/competidores/' . $domainSlug . '/organic-keywords.xlsx',
                $baseDir . '/competidores/' . $domainSlug . '/keywords.xlsx', // Competidores usan keywords.xlsx
            ];

            $xlsxFile = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $xlsxFile = $path;
                    break;
                }
            }

            if (!$xlsxFile) {
                $this->warn("No se encontró archivo de keywords para {$domain->domain}");
                continue;
            }

            $this->line("Procesando: {$domain->domain}");

            try {
                $spreadsheet = IOFactory::load($xlsxFile);
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = $sheet->getHighestRow();

                $count = 0;
                $skipped = 0;

                // Leer headers (fila 1)
                $headers = [];
                foreach ($sheet->getRowIterator(1, 1) as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    foreach ($cellIterator as $cell) {
                        $headers[] = $cell->getValue();
                    }
                }

                // Mapear columnas
                $keywordCol = array_search('Keyword', $headers) ?: array_search('keyword', $headers) ?: 0;
                $positionCol = array_search('Position', $headers) ?: array_search('position', $headers) ?: 1;
                $urlCol = array_search('URL', $headers) ?: array_search('url', $headers) ?: 2;
                $trafficCol = array_search('Traffic', $headers) ?: array_search('traffic', $headers);

                // Procesar filas
                for ($row = 2; $row <= $highestRow; $row++) {
                    if ($limit && $count >= $limit) {
                        break;
                    }

                    try {
                        $keywordName = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($keywordCol + 1) . $row)->getValue();
                        $position = (int)$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($positionCol + 1) . $row)->getValue();
                        $url = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($urlCol + 1) . $row)->getValue();
                        $traffic = $trafficCol !== false
                            ? (int)$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($trafficCol + 1) . $row)->getValue()
                            : null;

                        if (empty($keywordName) || $position < 1) {
                            $skipped++;
                            continue;
                        }

                        // Buscar o crear keyword
                        $keyword = $rankingImporter->findOrCreateKeyword($keywordName);

                        if (!$keyword) {
                            $skipped++;
                            continue;
                        }

                        // Importar ranking
                        $rankingImporter->importRanking([
                            'keyword_id' => $keyword->id,
                            'domain_id' => $domain->id,
                            'position' => $position,
                            'url' => $url,
                            'estimated_traffic' => $traffic,
                            'snapshot_date' => $snapshotDate,
                        ]);

                        $count++;
                        $totalRankings++;

                    } catch (\Exception $e) {
                        $skipped++;
                        if (config('seo.debug')) {
                            $this->warn("  Error en fila {$row}: " . $e->getMessage());
                        }
                    }
                }

                $this->info("  ✓ Importados: {$count}, Omitidos: {$skipped}");
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
                ['Rankings importados', $totalRankings],
                ['Dominios procesados', $totalDomains],
                ['Snapshot date', $snapshotDate],
            ]
        );

        return Command::SUCCESS;
    }
}
