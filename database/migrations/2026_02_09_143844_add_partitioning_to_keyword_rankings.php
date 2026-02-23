<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // El particionamiento solo está disponible en MySQL/MariaDB
        // Para SQLite (desarrollo local), se omite esta migration
        if (config('database.default') !== 'mysql') {
            // Silently skip partitioning on non-MySQL databases
            return;
        }

        // Verificar que la tabla existe antes de particionar
        $tableExists = DB::select("
            SELECT COUNT(*) as count
            FROM information_schema.tables
            WHERE table_schema = ?
            AND table_name = 'keyword_rankings'
        ", [config('database.connections.mysql.database')]);

        if ($tableExists[0]->count == 0) {
            throw new \Exception('Table keyword_rankings does not exist. Run core tables migration first.');
        }

        // MySQL requiere que la columna de partición sea parte de todas las claves únicas
        // La tabla ya tiene un unique constraint en (keyword_id, domain_id, snapshot_date)
        // pero necesitamos incluir snapshot_month para particionar por ese campo

        // Drop foreign keys — MySQL does not support FKs on partitioned tables
        DB::statement('ALTER TABLE keyword_rankings DROP FOREIGN KEY keyword_rankings_keyword_id_foreign');
        DB::statement('ALTER TABLE keyword_rankings DROP FOREIGN KEY keyword_rankings_domain_id_foreign');

        // MySQL requires the partition column to be part of ALL unique keys including PK.
        // Must drop and add PK in a single statement because id is auto-increment.
        DB::statement('ALTER TABLE keyword_rankings DROP PRIMARY KEY, ADD PRIMARY KEY (id, snapshot_month)');

        DB::statement('ALTER TABLE keyword_rankings DROP INDEX keyword_rankings_keyword_id_domain_id_snapshot_date_unique');
        DB::statement('ALTER TABLE keyword_rankings ADD UNIQUE KEY keyword_rankings_unique (keyword_id, domain_id, snapshot_date, snapshot_month)');

        // Aplicar particionamiento por mes
        DB::statement("
            ALTER TABLE keyword_rankings
            PARTITION BY RANGE COLUMNS(snapshot_month) (
                PARTITION p_2026_01 VALUES LESS THAN ('2026-02'),
                PARTITION p_2026_02 VALUES LESS THAN ('2026-03'),
                PARTITION p_2026_03 VALUES LESS THAN ('2026-04'),
                PARTITION p_2026_04 VALUES LESS THAN ('2026-05'),
                PARTITION p_2026_05 VALUES LESS THAN ('2026-06'),
                PARTITION p_2026_06 VALUES LESS THAN ('2026-07'),
                PARTITION p_2026_07 VALUES LESS THAN ('2026-08'),
                PARTITION p_2026_08 VALUES LESS THAN ('2026-09'),
                PARTITION p_2026_09 VALUES LESS THAN ('2026-10'),
                PARTITION p_2026_10 VALUES LESS THAN ('2026-11'),
                PARTITION p_2026_11 VALUES LESS THAN ('2026-12'),
                PARTITION p_2026_12 VALUES LESS THAN ('2027-01'),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Solo revertir si es MySQL
        if (config('database.default') !== 'mysql') {
            // Silently skip rollback on non-MySQL databases
            return;
        }

        DB::statement('ALTER TABLE keyword_rankings REMOVE PARTITIONING');

        DB::statement('ALTER TABLE keyword_rankings DROP PRIMARY KEY, ADD PRIMARY KEY (id)');

        DB::statement('ALTER TABLE keyword_rankings DROP INDEX keyword_rankings_unique');
        DB::statement('ALTER TABLE keyword_rankings ADD UNIQUE KEY keyword_rankings_keyword_id_domain_id_snapshot_date_unique (keyword_id, domain_id, snapshot_date)');

        // Restore foreign keys
        DB::statement('ALTER TABLE keyword_rankings ADD CONSTRAINT keyword_rankings_keyword_id_foreign FOREIGN KEY (keyword_id) REFERENCES keywords (id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE keyword_rankings ADD CONSTRAINT keyword_rankings_domain_id_foreign FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE');
    }
};
