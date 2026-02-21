<?php

namespace App\Console\Commands\Seo;

use App\Services\SemrushZipImporter;
use Illuminate\Console\Command;

class ImportZip extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:import:zip
                            {zip_path : Path to the SEMRush ZIP export file}
                            {--domain= : Domain to associate with this import}
                            {--snapshot-date= : Snapshot date (YYYY-MM-DD)}
                            {--force : Force overwrite of existing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar datos desde un archivo ZIP exportado de SEMRush';

    /**
     * Execute the console command.
     */
    public function handle(SemrushZipImporter $importer)
    {
        $zipPath = $this->argument('zip_path');

        // Validate ZIP file exists
        if (!file_exists($zipPath)) {
            $this->error("El archivo ZIP no existe: {$zipPath}");
            return Command::FAILURE;
        }

        $this->info('Importando datos desde archivo ZIP de SEMRush');
        $this->info("Archivo: {$zipPath}");
        $this->newLine();

        // Prepare options
        $options = [];

        if ($this->option('domain')) {
            $options['domain'] = $this->option('domain');
        }

        if ($this->option('snapshot-date')) {
            $options['snapshot-date'] = $this->option('snapshot-date');
        }

        if ($this->option('force')) {
            $options['force'] = true;
        }

        try {
            // Execute import
            $this->info('Extrayendo ZIP...');
            $result = $importer->import($zipPath, $options);

            // Display results
            $this->newLine();
            $this->info('✓ Importación completada exitosamente');
            $this->newLine();

            // Display changelog
            if (isset($result['changelog']['changes'])) {
                $this->info('RESUMEN DE CAMBIOS:');
                $this->line('-------------------');

                foreach ($result['changelog']['changes'] as $type => $changes) {
                    $this->line(strtoupper($type) . ':');
                    foreach ($changes as $action => $count) {
                        $this->line("  • {$action}: {$count}");
                    }
                }
            }

            $this->newLine();
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Import Log ID', $result['import_log_id']],
                    ['Duración (segundos)', $result['duration']],
                    ['Dominio', $result['changelog']['domain'] ?? 'N/A'],
                    ['Fecha Snapshot', $result['changelog']['snapshot_date'] ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error durante la importación:');
            $this->error($e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
