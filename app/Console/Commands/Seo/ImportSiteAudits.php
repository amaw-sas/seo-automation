<?php

namespace App\Console\Commands\Seo;

use App\Models\Domain;
use App\Models\SiteAudit;
use App\Models\SiteAuditIssue;
use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportSiteAudits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:site-audits
                            {--domain= : Specific domain to import (optional)}
                            {--audit-date= : Audit date (YYYY-MM-DD), defaults to 2026-01-25}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar site audits desde archivos XLSX';

    private array $errorColumns = [];
    private array $warningColumns = [];
    private array $noticeColumns = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainFilter = $this->option('domain');
        $auditDate = $this->option('audit-date') ?: '2026-01-25';

        try {
            Carbon::parse($auditDate);
        } catch (\Exception $e) {
            $this->error("Fecha de auditoría inválida: {$auditDate}");
            return Command::FAILURE;
        }

        $this->info('Importando site audits desde archivos XLSX');
        $this->newLine();

        // Clasificar tipos de issues
        $this->classifyIssueTypes();

        $baseDir = config('seo.semrush_data_dir');

        // Solo procesar dominios propios (los que tienen site-audit.xlsx)
        $domainsQuery = Domain::where('is_own', true);

        if ($domainFilter) {
            $domainsQuery->where('domain', $domainFilter);
        }

        $domains = $domainsQuery->get();

        if ($domains->isEmpty()) {
            $this->error("No se encontraron dominios para procesar");
            return Command::FAILURE;
        }

        $totalAudits = 0;
        $totalIssues = 0;

        foreach ($domains as $domain) {
            // Buscar archivo site-audit.xlsx
            $domainSlug = str_replace(['.com.co', '.com', '.co'], '', $domain->domain);
            $possiblePaths = [
                $baseDir . '/mis-dominios/' . $domainSlug . '/site-audit.xlsx',
            ];

            $xlsxFile = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $xlsxFile = $path;
                    break;
                }
            }

            if (!$xlsxFile) {
                $this->warn("No se encontró site-audit.xlsx para {$domain->domain}");
                continue;
            }

            $this->line("Procesando: {$domain->domain}");

            try {
                $reader = IOFactory::createReader('Xlsx');
                $spreadsheet = $reader->load($xlsxFile);
                $sheet = $spreadsheet->getActiveSheet();

                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Get headers (row 1)
                $headers = [];
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $value = $sheet->getCell($col . '1')->getValue();

                    // Handle RichText objects in headers
                    if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                        $value = $value->getPlainText();
                    }

                    if ($value) {
                        $headers[$col] = $value;
                    }
                }

                // Calculate metrics
                $pagesCrawled = $highestRow - 1; // Minus header
                $errors = 0;
                $warnings = 0;
                $notices = 0;
                $issuesSummary = [];

                // Iterate through issue columns (B to end)
                foreach ($headers as $col => $header) {
                    if ($col === 'A') continue; // Skip URL column

                    $totalIssuesForType = 0;
                    $affectedPages = 0;
                    $exampleUrl = null;

                    // Count issues in this column
                    for ($row = 2; $row <= $highestRow; $row++) {
                        $cellValue = $sheet->getCell($col . $row)->getValue();

                        // Handle RichText objects
                        if ($cellValue instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                            $cellValue = $cellValue->getPlainText();
                        }

                        $value = (int)$cellValue;
                        $totalIssuesForType += $value;

                        if ($value > 0) {
                            $affectedPages++;
                            if (!$exampleUrl) {
                                $urlValue = $sheet->getCell('A' . $row)->getValue();
                                if ($urlValue instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                                    $urlValue = $urlValue->getPlainText();
                                }
                                $exampleUrl = $urlValue;
                            }
                        }
                    }

                    if ($totalIssuesForType > 0) {
                        $issuesSummary[$header] = $totalIssuesForType;

                        // Classify by severity
                        $severity = $this->classifySeverity($header);
                        if ($severity === 'error') {
                            $errors += $affectedPages;
                        } elseif ($severity === 'warning') {
                            $warnings += $affectedPages;
                        } else {
                            $notices += $affectedPages;
                        }
                    }
                }

                // Calculate health score (simple formula: 100 - (errors*5 + warnings*2 + notices))
                $healthScore = max(0, 100 - ($errors * 5 + $warnings * 2 + $notices));

                // Create or update site audit
                $siteAudit = SiteAudit::updateOrCreate(
                    [
                        'domain_id' => $domain->id,
                        'audit_date' => $auditDate,
                    ],
                    [
                        'pages_crawled' => $pagesCrawled,
                        'site_health_score' => $healthScore,
                        'errors' => $errors,
                        'warnings' => $warnings,
                        'notices' => $notices,
                        'audit_summary' => $issuesSummary,
                    ]
                );

                // Delete old issues and create new ones
                SiteAuditIssue::where('site_audit_id', $siteAudit->id)->delete();

                $issuesCreated = 0;
                foreach ($issuesSummary as $issueType => $totalIssues) {
                    // Find example URL and affected pages
                    $affectedPages = 0;
                    $exampleUrl = null;

                    foreach ($headers as $col => $header) {
                        if ($header === $issueType) {
                            for ($row = 2; $row <= $highestRow; $row++) {
                                $cellValue = $sheet->getCell($col . $row)->getValue();
                                if ($cellValue instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                                    $cellValue = $cellValue->getPlainText();
                                }

                                $value = (int)$cellValue;
                                if ($value > 0) {
                                    $affectedPages++;
                                    if (!$exampleUrl) {
                                        $urlValue = $sheet->getCell('A' . $row)->getValue();
                                        if ($urlValue instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                                            $urlValue = $urlValue->getPlainText();
                                        }
                                        $exampleUrl = $urlValue;
                                    }
                                }
                            }
                            break;
                        }
                    }

                    SiteAuditIssue::create([
                        'site_audit_id' => $siteAudit->id,
                        'issue_type' => $issueType,
                        'severity' => $this->classifySeverity($issueType),
                        'description' => "Se encontraron {$totalIssues} instancias de '{$issueType}'",
                        'affected_pages' => $affectedPages,
                        'example_url' => $exampleUrl,
                    ]);

                    $issuesCreated++;
                }

                $this->info("  ✓ Audit importado: {$pagesCrawled} páginas, {$issuesCreated} tipos de issues, Health Score: {$healthScore}");
                $totalAudits++;
                $totalIssues += $issuesCreated;

            } catch (\Exception $e) {
                $this->error("  Error procesando {$domain->domain}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("RESUMEN:");
        $this->table(
            ['Métrica', 'Total'],
            [
                ['Audits Importados', $totalAudits],
                ['Tipos de Issues', $totalIssues],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Classify issue severity based on type name
     */
    private function classifySeverity(string $issueType): string
    {
        $issueTypeLower = strtolower($issueType);

        // Errors: critical issues
        if (str_contains($issueTypeLower, 'error') ||
            str_contains($issueTypeLower, 'missing') ||
            str_contains($issueTypeLower, 'broken') ||
            str_contains($issueTypeLower, '5xx') ||
            str_contains($issueTypeLower, '4xx')) {
            return 'error';
        }

        // Warnings: important but not critical
        if (str_contains($issueTypeLower, 'duplicate') ||
            str_contains($issueTypeLower, 'redirect') ||
            str_contains($issueTypeLower, 'slow') ||
            str_contains($issueTypeLower, 'large')) {
            return 'warning';
        }

        // Notices: best practices
        return 'notice';
    }

    /**
     * Classify issue types (for reference)
     */
    private function classifyIssueTypes(): void
    {
        $this->errorColumns = [
            '5xx errors',
            '4xx errors',
            'Title tag is missing or empty',
            'Broken internal links',
            'DNS resolution issue',
        ];

        $this->warningColumns = [
            'Duplicate title tag',
            'Duplicate content',
            'Pages not crawled',
        ];

        $this->noticeColumns = [
            'Meta description is missing or empty',
            'H1 tag is missing or empty',
        ];
    }
}
