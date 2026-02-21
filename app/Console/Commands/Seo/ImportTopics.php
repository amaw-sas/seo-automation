<?php

namespace App\Console\Commands\Seo;

use App\Models\City;
use App\Models\TopicResearch;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportTopics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:topics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar topic research desde archivos XLSX';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Importando topic research desde archivos XLSX');
        $this->newLine();

        $baseDir = config('seo.semrush_data_dir');
        $topicResearchDir = $baseDir . '/contenido/topic-research';

        if (!is_dir($topicResearchDir)) {
            $this->error("No se encontró el directorio: {$topicResearchDir}");
            return Command::FAILURE;
        }

        // Find all XLSX files
        $xlsxFiles = glob($topicResearchDir . '/*.xlsx');

        if (empty($xlsxFiles)) {
            $this->error("No se encontraron archivos XLSX en {$topicResearchDir}");
            return Command::FAILURE;
        }

        $totalTopics = 0;
        $totalCreated = 0;
        $totalUpdated = 0;

        foreach ($xlsxFiles as $xlsxFile) {
            $filename = basename($xlsxFile);
            $this->line("Procesando: {$filename}");

            // Detect city from filename
            $cityId = $this->detectCityFromFilename($filename);

            try {
                $reader = IOFactory::createReader('Xlsx');
                $spreadsheet = $reader->load($xlsxFile);
                $sheet = $spreadsheet->getActiveSheet();

                $highestRow = $sheet->getHighestRow();

                // Group by Topic
                $topicsData = [];

                for ($row = 2; $row <= $highestRow; $row++) {
                    $topic = $this->getCellValue($sheet, 'A', $row); // Topic
                    $subtopic = $this->getCellValue($sheet, 'B', $row); // Subtopic
                    $searchVolume = (int)$this->getCellValue($sheet, 'C', $row); // Search Volume
                    $difficulty = (float)$this->getCellValue($sheet, 'D', $row); // Difficulty
                    $topicEfficiency = $this->getCellValue($sheet, 'E', $row); // Topic Efficiency
                    $contentIdea = $this->getCellValue($sheet, 'F', $row); // Content Idea

                    if (empty($topic)) {
                        continue;
                    }

                    if (!isset($topicsData[$topic])) {
                        $topicsData[$topic] = [
                            'title' => $topic,
                            'city_id' => $cityId,
                            'search_volumes' => [],
                            'difficulties' => [],
                            'subtopics' => [],
                            'content_ideas' => [],
                        ];
                    }

                    // Accumulate data
                    if ($searchVolume > 0) {
                        $topicsData[$topic]['search_volumes'][] = $searchVolume;
                    }

                    if ($difficulty > 0) {
                        $topicsData[$topic]['difficulties'][] = $difficulty;
                    }

                    if ($subtopic && !in_array($subtopic, $topicsData[$topic]['subtopics'])) {
                        $topicsData[$topic]['subtopics'][] = $subtopic;
                    }

                    if ($contentIdea && !in_array($contentIdea, $topicsData[$topic]['content_ideas'])) {
                        $topicsData[$topic]['content_ideas'][] = $contentIdea;
                    }
                }

                // Create or update topics
                $imported = 0;
                $created = 0;
                $updated = 0;

                foreach ($topicsData as $topicData) {
                    // Calculate averages
                    $potentialTraffic = !empty($topicData['search_volumes'])
                        ? (int)array_sum($topicData['search_volumes']) / count($topicData['search_volumes'])
                        : null;

                    $competitionLevel = !empty($topicData['difficulties'])
                        ? (int)array_sum($topicData['difficulties']) / count($topicData['difficulties'])
                        : null;

                    // Prepare data
                    $data = [
                        'title' => $topicData['title'],
                        'city_id' => $topicData['city_id'],
                        'potential_traffic' => $potentialTraffic,
                        'competition_level' => $competitionLevel,
                        'recommended_keywords' => array_slice($topicData['subtopics'], 0, 20), // Max 20
                        'content_outline' => !empty($topicData['content_ideas'])
                            ? implode("\n", array_slice($topicData['content_ideas'], 0, 10))
                            : null,
                    ];

                    // Check if exists
                    $existing = TopicResearch::where('title', $topicData['title'])
                        ->where('city_id', $topicData['city_id'])
                        ->first();

                    if ($existing) {
                        $existing->update($data);
                        $updated++;
                    } else {
                        TopicResearch::create($data);
                        $created++;
                    }

                    $imported++;
                }

                $this->info("  ✓ Importados: {$imported} topics (Creados: {$created}, Actualizados: {$updated})");
                $totalTopics += $imported;
                $totalCreated += $created;
                $totalUpdated += $updated;

            } catch (\Exception $e) {
                $this->error("  Error procesando {$filename}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("RESUMEN:");
        $this->table(
            ['Métrica', 'Total'],
            [
                ['Topics Importados', $totalTopics],
                ['Nuevos Creados', $totalCreated],
                ['Actualizados', $totalUpdated],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Get cell value handling RichText
     */
    private function getCellValue($sheet, string $col, int $row)
    {
        $value = $sheet->getCell($col . $row)->getValue();

        if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $value = $value->getPlainText();
        }

        return $value;
    }

    /**
     * Detect city from filename
     */
    private function detectCityFromFilename(string $filename): ?int
    {
        $filenameLower = strtolower($filename);

        // Map of city names to search for
        $cityMap = [
            'bogota' => 'Bogotá',
            'medellin' => 'Medellín',
            'cali' => 'Cali',
            'barranquilla' => 'Barranquilla',
            'cartagena' => 'Cartagena',
            'cucuta' => 'Cúcuta',
            'bucaramanga' => 'Bucaramanga',
            'pereira' => 'Pereira',
            'santa marta' => 'Santa Marta',
            'ibague' => 'Ibagué',
            'villavicencio' => 'Villavicencio',
            'manizales' => 'Manizales',
            'neiva' => 'Neiva',
            'armenia' => 'Armenia',
            'pasto' => 'Pasto',
            'monteria' => 'Montería',
            'popayan' => 'Popayán',
            'valledupar' => 'Valledupar',
            'riohacha' => 'Riohacha',
        ];

        foreach ($cityMap as $search => $cityName) {
            if (str_contains($filenameLower, $search)) {
                $city = City::where('name', $cityName)->first();
                return $city?->id;
            }
        }

        // If contains "colombia" but no specific city, return null (national)
        if (str_contains($filenameLower, 'colombia')) {
            return null;
        }

        return null;
    }
}
