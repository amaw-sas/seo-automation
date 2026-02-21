<?php

namespace App\Console\Commands\Seo;

use App\Models\Domain;
use App\Models\GapType;
use App\Models\Keyword;
use App\Models\KeywordGap;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportKeywordGaps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:keyword-gaps
                            {--file= : Specific file to import (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar keyword gaps desde archivos XLSX';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specificFile = $this->option('file');

        $this->info('Importando keyword gaps desde archivos XLSX');
        $this->newLine();

        $baseDir = config('seo.semrush_data_dir') . '/keywords';

        // Buscar archivos keyword-gap
        if ($specificFile) {
            $files = [$baseDir . '/' . $specificFile];
        } else {
            $files = glob($baseDir . '/keywords-gap-*.xlsx');
        }

        if (empty($files)) {
            $this->error("No se encontraron archivos de keyword gaps");
            return Command::FAILURE;
        }

        $this->info("Encontrados " . count($files) . " archivos");
        $this->newLine();

        // Obtener nuestros dominios (is_own = true)
        $ourDomains = Domain::where('is_own', true)->get();
        if ($ourDomains->isEmpty()) {
            $this->error("No se encontraron dominios propios");
            return Command::FAILURE;
        }

        $totalGaps = 0;
        $totalSkipped = 0;

        foreach ($files as $file) {
            $filename = basename($file);
            $this->line("Procesando: {$filename}");

            try {
                // Detectar gap type desde nombre de archivo
                $gapType = $this->detectGapType($filename);
                if (!$gapType) {
                    $this->warn("  No se pudo detectar gap type, usando 'missing'");
                    $gapType = GapType::where('slug', 'missing')->first();
                }

                // Leer XLSX
                $spreadsheet = IOFactory::load($file);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                // Primera fila = headers
                $headers = array_shift($rows);

                $imported = 0;
                $skipped = 0;

                // Detectar estructura del archivo
                $hasOurPosition = in_array('Our Position', $headers);
                $hasMultiDomain = $this->detectMultiDomainStructure($headers);

                if ($hasMultiDomain && !$hasOurPosition) {
                    // Estructura multi-dominio (archivos "débiles")
                    $result = $this->processMultiDomainStructure($rows, $headers, $gapType, $ourDomains);
                    $imported = $result['imported'];
                    $skipped = $result['skipped'];
                } else {
                    // Estructura estándar (archivos "faltantes" y "compartidas")
                    foreach ($rows as $row) {
                        try {
                            // Mapear columnas
                            $record = [];
                            foreach ($headers as $index => $header) {
                                $record[$header] = $row[$index] ?? null;
                            }

                            // Extraer keyword
                            $keywordText = $record['Keyword'] ?? $record['keyword'] ?? null;
                            if (empty($keywordText)) {
                                $skipped++;
                                continue;
                            }

                            // Buscar keyword en BD (o crear si no existe)
                            $keyword = Keyword::where('keyword_normalized', strtolower(trim($keywordText)))
                                ->first();

                            if (!$keyword) {
                                // Crear keyword básica
                                $keyword = Keyword::create([
                                    'keyword' => $keywordText,
                                    'keyword_normalized' => strtolower(trim($keywordText)),
                                    'search_volume_co' => (int)($record['Volume'] ?? $record['Search Volume'] ?? 0),
                                    'keyword_difficulty' => isset($record['Keyword Difficulty']) ? (float)$record['Keyword Difficulty'] : null,
                                ]);
                            }

                            // Detectar competitor domain desde nombre de archivo o columnas
                            $competitorDomainName = $this->detectCompetitorDomain($filename, $record);
                            $competitorDomain = null;

                            if ($competitorDomainName) {
                                $competitorDomain = Domain::where('domain', 'like', '%' . $competitorDomainName . '%')->first();
                            }

                            // Si no hay competidor específico, usar el primero que no sea propio
                            if (!$competitorDomain) {
                                $competitorDomain = Domain::where('is_own', false)->first();
                            }

                            if (!$competitorDomain) {
                                $skipped++;
                                continue;
                            }

                            // Crear gaps para cada dominio propio
                            foreach ($ourDomains as $ourDomain) {
                                // Extraer posiciones
                                $ourPosition = isset($record['Our Position']) ? (int)$record['Our Position'] : null;
                                $competitorPosition = isset($record['Competitor Position'])
                                    ? (int)$record['Competitor Position']
                                    : (isset($record['Position']) ? (int)$record['Position'] : null);

                                // Calcular position difference
                                $positionDifference = null;
                                if ($ourPosition && $competitorPosition) {
                                    $positionDifference = $ourPosition - $competitorPosition;
                                }

                                // Calcular opportunity score
                                $opportunityScore = $this->calculateOpportunityScore(
                                    $keyword->search_volume_co,
                                    $keyword->keyword_difficulty,
                                    $positionDifference,
                                    $gapType->slug
                                );

                                // Crear o actualizar gap
                                KeywordGap::updateOrCreate(
                                    [
                                        'keyword_id' => $keyword->id,
                                        'our_domain_id' => $ourDomain->id,
                                        'competitor_domain_id' => $competitorDomain->id,
                                        'gap_type_id' => $gapType->id,
                                    ],
                                    [
                                        'our_position' => $ourPosition,
                                        'competitor_position' => $competitorPosition,
                                        'position_difference' => $positionDifference,
                                        'opportunity_score' => $opportunityScore,
                                        'analysis_date' => now()->toDateString(),
                                    ]
                                );

                                $imported++;
                            }

                        } catch (\Exception $e) {
                            $skipped++;
                            if (config('seo.debug')) {
                                $this->warn("  Error en row: " . $e->getMessage());
                            }
                        }
                    }
                }

                $this->info("  ✓ Importados: {$imported}, Omitidos: {$skipped}");
                $totalGaps += $imported;
                $totalSkipped += $skipped;

            } catch (\Exception $e) {
                $this->error("  Error procesando {$filename}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("✓ Importación completada");
        $this->table(
            ['Métrica', 'Total'],
            [
                ['Gaps importados', $totalGaps],
                ['Filas omitidas', $totalSkipped],
                ['Archivos procesados', count($files)],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Detect gap type from filename
     */
    private function detectGapType(string $filename): ?GapType
    {
        if (stripos($filename, 'faltantes') !== false) {
            return GapType::where('slug', 'missing')->first();
        }

        if (stripos($filename, 'debiles') !== false || stripos($filename, 'deviles') !== false) {
            return GapType::where('slug', 'weak')->first();
        }

        if (stripos($filename, 'compartidas') !== false) {
            return GapType::where('slug', 'shared')->first();
        }

        return null;
    }

    /**
     * Detect competitor domain from filename or record
     */
    private function detectCompetitorDomain(string $filename, array $record): ?string
    {
        // Try from filename
        $competitorNames = [
            'localiza', 'alkilautos', 'autoalquilados', 'executiverentacar',
            'evolution', 'gorentacar', 'rentingcolombia', 'equirent',
            'hertz', 'avis', 'kayak', 'rentalcars', 'despegar'
        ];

        foreach ($competitorNames as $name) {
            if (stripos($filename, $name) !== false) {
                return $name;
            }
        }

        // Try from record (if there's a "Domain" column)
        if (isset($record['Domain']) && !empty($record['Domain'])) {
            return $record['Domain'];
        }

        return null;
    }

    /**
     * Detect multi-domain structure (débiles files)
     */
    private function detectMultiDomainStructure(array $headers): bool
    {
        // Buscar columnas que parezcan dominios (contienen ".com" o ".co")
        $domainColumns = array_filter($headers, function($header) {
            return stripos($header, '.com') !== false || stripos($header, '.co') !== false;
        });

        return count($domainColumns) >= 2;
    }

    /**
     * Process multi-domain structure (débiles files)
     */
    private function processMultiDomainStructure(
        array $rows,
        array $headers,
        GapType $gapType,
        $ourDomains
    ): array {
        $imported = 0;
        $skipped = 0;

        // Identificar columnas de dominios (excluir columnas "(pages)")
        $domainColumns = [];
        foreach ($headers as $index => $header) {
            if ((stripos($header, '.com') !== false || stripos($header, '.co') !== false)
                && stripos($header, '(pages)') === false) {
                $domainColumns[$index] = $header;
            }
        }

        // Obtener todos los dominios de la BD
        $allDomains = Domain::all();

        // Mapeo manual para nombres alternativos de dominios
        $domainAliases = [
            'alquilatucarro.com' => 'alquilatucarro.com.co',
            'alquilame.com' => 'alquilame.com.co',
            'alquicarros.co' => 'alquicarros.co',
            'rentcars.com' => 'rentalcars.com',
        ];

        foreach ($rows as $row) {
            try {
                // Mapear columnas
                $record = [];
                foreach ($headers as $index => $header) {
                    $record[$header] = $row[$index] ?? null;
                }

                // Extraer keyword
                $keywordText = $record['Keyword'] ?? null;
                if (empty($keywordText)) {
                    $skipped++;
                    continue;
                }

                // Buscar o crear keyword
                $keyword = Keyword::where('keyword_normalized', strtolower(trim($keywordText)))
                    ->first();

                if (!$keyword) {
                    $keyword = Keyword::create([
                        'keyword' => $keywordText,
                        'keyword_normalized' => strtolower(trim($keywordText)),
                        'search_volume_co' => (int)($record['Volume'] ?? 0),
                        'keyword_difficulty' => isset($record['Keyword Difficulty']) ? (float)$record['Keyword Difficulty'] : null,
                    ]);
                }

                // Parsear posiciones de cada dominio
                $positions = [];
                foreach ($domainColumns as $index => $domainName) {
                    $position = $row[$index] ?? null;
                    if ($position !== null && $position !== '' && is_numeric($position)) {
                        // Normalizar nombre de dominio usando aliases
                        $normalizedName = strtolower($domainName);
                        if (isset($domainAliases[$normalizedName])) {
                            $normalizedName = $domainAliases[$normalizedName];
                        }
                        $positions[$normalizedName] = (int)$position;
                    }
                }

                // Crear gaps para cada combinación (nuestros dominios vs competidores)
                foreach ($ourDomains as $ourDomain) {
                    $ourDomainKey = strtolower($ourDomain->domain);
                    $ourPosition = $positions[$ourDomainKey] ?? null;

                    if ($ourPosition === null) {
                        continue; // No tenemos posición para este dominio propio
                    }

                    foreach ($positions as $domainKey => $competitorPosition) {
                        // Buscar el dominio en la BD
                        $competitorDomain = $allDomains->firstWhere(function($d) use ($domainKey) {
                            return strtolower($d->domain) === $domainKey;
                        });

                        if (!$competitorDomain || $competitorDomain->is_own) {
                            continue; // Skip si es nuestro dominio o no existe
                        }

                        // Calcular position difference
                        $positionDifference = $ourPosition - $competitorPosition;

                        // Calcular opportunity score
                        $opportunityScore = $this->calculateOpportunityScore(
                            $keyword->search_volume_co,
                            $keyword->keyword_difficulty,
                            $positionDifference,
                            $gapType->slug
                        );

                        // Crear o actualizar gap
                        KeywordGap::updateOrCreate(
                            [
                                'keyword_id' => $keyword->id,
                                'our_domain_id' => $ourDomain->id,
                                'competitor_domain_id' => $competitorDomain->id,
                                'gap_type_id' => $gapType->id,
                            ],
                            [
                                'our_position' => $ourPosition,
                                'competitor_position' => $competitorPosition,
                                'position_difference' => $positionDifference,
                                'opportunity_score' => $opportunityScore,
                                'analysis_date' => now()->toDateString(),
                            ]
                        );

                        $imported++;
                    }
                }

            } catch (\Exception $e) {
                $skipped++;
                if (config('seo.debug')) {
                    $this->warn("  Error processing row: " . $e->getMessage());
                }
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    /**
     * Calculate opportunity score
     * Score 0-100 based on volume, KD, position difference, and gap type
     */
    private function calculateOpportunityScore(
        int $volume,
        ?float $kd,
        ?int $positionDiff,
        string $gapTypeSlug
    ): int {
        $score = 0;

        // Volume contribution (0-40 points)
        if ($volume >= 1000) {
            $score += 40;
        } elseif ($volume >= 500) {
            $score += 30;
        } elseif ($volume >= 100) {
            $score += 20;
        } elseif ($volume >= 50) {
            $score += 10;
        }

        // KD contribution (0-30 points, inverse - lower is better)
        if ($kd !== null) {
            if ($kd <= 20) {
                $score += 30;
            } elseif ($kd <= 40) {
                $score += 20;
            } elseif ($kd <= 60) {
                $score += 10;
            }
        }

        // Position difference contribution (0-20 points)
        if ($positionDiff !== null) {
            if ($positionDiff >= 20) {
                $score += 20; // We're much worse - big opportunity
            } elseif ($positionDiff >= 10) {
                $score += 15;
            } elseif ($positionDiff >= 5) {
                $score += 10;
            } elseif ($positionDiff > 0) {
                $score += 5;
            }
        }

        // Gap type bonus (0-10 points)
        if ($gapTypeSlug === 'missing') {
            $score += 10; // Missing keywords are high priority
        } elseif ($gapTypeSlug === 'weak') {
            $score += 7;
        } elseif ($gapTypeSlug === 'untapped') {
            $score += 8;
        }

        return min($score, 100);
    }
}
