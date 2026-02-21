<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\ImportLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class SemrushZipImporter
{
    private string $tempDir;
    private ?ImportLog $importLog = null;

    /**
     * Import data from a SEMRush ZIP export
     *
     * @param string $zipPath Path to the ZIP file
     * @param array $options Import options
     * @return array Import result
     */
    public function import(string $zipPath, array $options = []): array
    {
        $startTime = now();

        // 1. Validate ZIP
        if (!file_exists($zipPath)) {
            throw new \Exception("ZIP file not found: {$zipPath}");
        }

        // 2. Extract to temporary directory
        $this->tempDir = $this->extractToTemp($zipPath);

        // 3. Read metadata
        $metadata = $this->readMetadata($this->tempDir);

        // 4. Detect domain
        $domainName = $options['domain'] ?? $metadata['domain'] ?? null;
        $domain = $this->getDomain($domainName);

        // 5. Create import log
        $snapshotDate = $options['snapshot-date'] ?? $metadata['export_date'] ?? now()->format('Y-m-d');
        $this->importLog = ImportLog::create([
            'domain_id' => $domain?->id,
            'import_type' => 'zip',
            'source_file' => basename($zipPath),
            'snapshot_date' => $snapshotDate,
            'status' => 'processing',
        ]);

        try {
            // 6. Import data types
            $results = [];

            if ($this->hasKeywordsData($this->tempDir)) {
                $results['keywords'] = $this->importKeywords($this->tempDir, $domain);
            }

            if ($this->hasRankingsData($this->tempDir)) {
                $results['rankings'] = $this->importRankings($this->tempDir, $domain, $snapshotDate);
            }

            if ($this->hasBacklinksData($this->tempDir)) {
                $results['backlinks'] = $this->importBacklinks($this->tempDir, $domain);
            }

            if ($this->hasPagesData($this->tempDir)) {
                $results['pages'] = $this->importPages($this->tempDir, $domain, $snapshotDate);
            }

            // 7. Generate changelog
            $changelog = $this->generateChangelog($results, $domain);

            // 8. Update counters in import log
            $this->updateImportLogCounters($results);

            // 9. Mark as completed
            $this->importLog->markAsCompleted($changelog);

            // 10. Cleanup
            $this->cleanupTemp($this->tempDir);

            return [
                'success' => true,
                'import_log_id' => $this->importLog->id,
                'results' => $results,
                'changelog' => $changelog,
                'duration' => $startTime->diffInSeconds(now()),
            ];

        } catch (\Exception $e) {
            // Mark as failed
            $this->importLog->markAsFailed($e->getMessage());

            // Cleanup on error
            $this->cleanupTemp($this->tempDir);

            throw $e;
        }
    }

    /**
     * Extract ZIP to temporary directory
     */
    private function extractToTemp(string $zipPath): string
    {
        $tempDir = sys_get_temp_dir() . '/semrush-import-' . uniqid();
        File::makeDirectory($tempDir, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \Exception("Failed to open ZIP file: {$zipPath}");
        }

        $zip->extractTo($tempDir);
        $zip->close();

        return $tempDir;
    }

    /**
     * Read metadata.json from extracted ZIP
     */
    private function readMetadata(string $tempDir): array
    {
        $metadataPath = $tempDir . '/metadata.json';

        if (!file_exists($metadataPath)) {
            // Return default metadata if file doesn't exist
            return [
                'export_date' => now()->format('Y-m-d'),
                'data_types' => ['keywords', 'rankings', 'backlinks', 'pages'],
            ];
        }

        return json_decode(file_get_contents($metadataPath), true);
    }

    /**
     * Get domain by name
     */
    private function getDomain(?string $domainName): ?Domain
    {
        if (!$domainName) {
            return null;
        }

        return Domain::where('domain', 'LIKE', '%' . $domainName . '%')->first();
    }

    /**
     * Check if ZIP has keywords data
     */
    private function hasKeywordsData(string $tempDir): bool
    {
        return !empty(glob($tempDir . '/keywords/*.{csv,xlsx}', GLOB_BRACE));
    }

    /**
     * Check if ZIP has rankings data
     */
    private function hasRankingsData(string $tempDir): bool
    {
        return !empty(glob($tempDir . '/rankings/*.{csv,xlsx}', GLOB_BRACE)) ||
               !empty(glob($tempDir . '/*organic-keywords*.{csv,xlsx}', GLOB_BRACE));
    }

    /**
     * Check if ZIP has backlinks data
     */
    private function hasBacklinksData(string $tempDir): bool
    {
        return !empty(glob($tempDir . '/backlinks/*.csv', GLOB_BRACE));
    }

    /**
     * Check if ZIP has pages data
     */
    private function hasPagesData(string $tempDir): bool
    {
        return !empty(glob($tempDir . '/pages/*.{csv,xlsx}', GLOB_BRACE)) ||
               !empty(glob($tempDir . '/*top-pages*.{csv,xlsx}', GLOB_BRACE));
    }

    /**
     * Import keywords from extracted ZIP
     */
    private function importKeywords(string $tempDir, ?Domain $domain): array
    {
        // This would call the existing import command
        // For now, return placeholder
        return ['imported' => 0, 'updated' => 0];
    }

    /**
     * Import rankings from extracted ZIP
     */
    private function importRankings(string $tempDir, ?Domain $domain, string $snapshotDate): array
    {
        // This would call the existing import command
        return ['imported' => 0, 'updated' => 0];
    }

    /**
     * Import backlinks from extracted ZIP
     */
    private function importBacklinks(string $tempDir, ?Domain $domain): array
    {
        // This would call the existing import command
        return ['imported' => 0, 'new' => 0, 'deactivated' => 0];
    }

    /**
     * Import pages from extracted ZIP
     */
    private function importPages(string $tempDir, ?Domain $domain, string $snapshotDate): array
    {
        // This would call the existing import command
        return ['imported' => 0, 'updated' => 0];
    }

    /**
     * Generate changelog from import results
     */
    private function generateChangelog(array $results, ?Domain $domain): array
    {
        $changelog = [
            'domain' => $domain?->domain ?? 'Unknown',
            'snapshot_date' => now()->format('Y-m-d'),
            'changes' => [],
        ];

        if (isset($results['keywords'])) {
            $changelog['changes']['keywords'] = [
                'added' => $results['keywords']['imported'] ?? 0,
                'updated' => $results['keywords']['updated'] ?? 0,
            ];
        }

        if (isset($results['rankings'])) {
            $changelog['changes']['rankings'] = [
                'added' => $results['rankings']['imported'] ?? 0,
                'updated' => $results['rankings']['updated'] ?? 0,
            ];
        }

        if (isset($results['backlinks'])) {
            $changelog['changes']['backlinks'] = [
                'new' => $results['backlinks']['new'] ?? 0,
                'lost' => $results['backlinks']['deactivated'] ?? 0,
            ];
        }

        return $changelog;
    }

    /**
     * Update import log counters
     */
    private function updateImportLogCounters(array $results): void
    {
        $updates = [];

        if (isset($results['keywords'])) {
            $updates['keywords_added'] = $results['keywords']['imported'] ?? 0;
            $updates['keywords_updated'] = $results['keywords']['updated'] ?? 0;
        }

        if (isset($results['rankings'])) {
            $updates['rankings_added'] = $results['rankings']['imported'] ?? 0;
            $updates['rankings_updated'] = $results['rankings']['updated'] ?? 0;
        }

        if (isset($results['backlinks'])) {
            $updates['backlinks_added'] = $results['backlinks']['new'] ?? 0;
            $updates['backlinks_deactivated'] = $results['backlinks']['deactivated'] ?? 0;
        }

        if (!empty($updates)) {
            $this->importLog->update($updates);
        }
    }

    /**
     * Cleanup temporary directory
     */
    private function cleanupTemp(string $tempDir): void
    {
        if (File::exists($tempDir)) {
            File::deleteDirectory($tempDir);
        }
    }
}
