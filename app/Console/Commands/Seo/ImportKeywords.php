<?php

namespace App\Console\Commands\Seo;

use App\Models\Keyword;
use App\Models\SearchIntent;
use App\Services\Importers\KeywordImporter;
use App\Services\Parsers\SerpFeaturesParser;
use Illuminate\Console\Command;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportKeywords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:keywords
                            {--source=magic-tool : Source directory (magic-tool)}
                            {--limit= : Limit number of keywords to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar keywords desde archivos CSV de SEMRush';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $source = $this->option('source');
        $limit = $this->option('limit');

        $this->info('Importando keywords desde SEMRush...');
        $this->newLine();

        $baseDir = config('seo.semrush_data_dir') . '/keywords';

        if ($source === 'magic-tool') {
            $sourceDir = $baseDir . '/magic-tool';
        } else {
            $sourceDir = $baseDir;
        }

        if (!is_dir($sourceDir)) {
            $this->error("Directorio no encontrado: {$sourceDir}");
            return Command::FAILURE;
        }

        // Buscar archivos CSV y XLSX
        $csvFiles = glob($sourceDir . '/*.csv');
        $xlsxFiles = glob($sourceDir . '/*.xlsx');
        $allFiles = array_merge($csvFiles, $xlsxFiles);

        if (empty($allFiles)) {
            $this->error("No se encontraron archivos CSV/XLSX en {$sourceDir}");
            return Command::FAILURE;
        }

        $this->info("Encontrados " . count($csvFiles) . " archivos CSV y " . count($xlsxFiles) . " archivos XLSX");
        $this->newLine();

        $importer = new KeywordImporter();
        $totalImported = 0;
        $totalSkipped = 0;

        foreach ($allFiles as $file) {
            $filename = basename($file);
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $this->line("Procesando: {$filename}");

            try {
                // Leer archivo según extensión
                if ($extension === 'csv') {
                    $csv = Reader::createFromPath($file, 'r');
                    $csv->setHeaderOffset(0);
                    $records = iterator_to_array($csv->getRecords());
                } elseif ($extension === 'xlsx') {
                    $spreadsheet = IOFactory::load($file);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();

                    // Primera fila = headers
                    $headers = array_shift($rows);

                    // Convertir a formato asociativo
                    $records = [];
                    foreach ($rows as $row) {
                        $record = [];
                        foreach ($headers as $index => $header) {
                            $record[$header] = $row[$index] ?? null;
                        }
                        $records[] = $record;
                    }
                } else {
                    continue;
                }

                $count = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if ($limit && $totalImported >= $limit) {
                        break 2; // Salir de ambos loops
                    }

                    try {
                        // Mapear columnas del CSV
                        $keywordData = [
                            'keyword' => $record['Keyword'] ?? $record['keyword'] ?? null,
                            'search_volume_co' => (int)($record['Volume'] ?? $record['Search Volume'] ?? 0),
                            'keyword_difficulty' => isset($record['Keyword Difficulty'])
                                ? (float)$record['Keyword Difficulty']
                                : null,
                            'cpc_usd' => isset($record['CPC (USD)'])
                                ? (float)$record['CPC (USD)']
                                : null,
                            'serp_features' => isset($record['SERP Features'])
                                ? SerpFeaturesParser::parse($record['SERP Features'])
                                : null,
                        ];

                        if (empty($keywordData['keyword'])) {
                            $skipped++;
                            continue;
                        }

                        // Auto-detectar ciudad, categoría e intent
                        $keywordData['city_id'] = $importer->detectCityFromKeyword($keywordData['keyword']);
                        $keywordData['category_id'] = $importer->detectCategoryFromKeyword($keywordData['keyword'], $filename);

                        // Detectar intent desde CSV o auto-detectar
                        if (isset($record['Intent'])) {
                            $intentSlug = strtolower(str_replace(' ', '_', $record['Intent']));
                            $keywordData['intent_id'] = SearchIntent::where('slug', 'like', '%' . $intentSlug . '%')->value('id');
                        }

                        if (!$keywordData['intent_id']) {
                            $keywordData['intent_id'] = $importer->detectIntentFromKeyword($keywordData['keyword']);
                        }

                        $importer->importKeyword($keywordData);
                        $count++;
                        $totalImported++;

                    } catch (\Exception $e) {
                        $skipped++;
                        $totalSkipped++;
                        if (config('seo.debug')) {
                            $this->warn("  Error en keyword: " . $e->getMessage());
                        }
                    }
                }

                $this->info("  ✓ Importadas: {$count}, Omitidas: {$skipped}");

            } catch (\Exception $e) {
                $this->error("  Error procesando {$filename}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("✓ Importación completada");
        $this->table(
            ['Métrica', 'Total'],
            [
                ['Keywords importadas', $totalImported],
                ['Keywords omitidas', $totalSkipped],
                ['Archivos procesados', count($allFiles)],
            ]
        );

        return Command::SUCCESS;
    }
}
