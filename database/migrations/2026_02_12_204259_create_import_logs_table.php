<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('domain_id')->nullable();
            $table->enum('import_type', ['zip', 'manual', 'api'])->default('manual');
            $table->string('source_file', 500)->nullable();
            $table->date('snapshot_date');

            // Contadores
            $table->unsignedInteger('keywords_added')->default(0);
            $table->unsignedInteger('keywords_updated')->default(0);
            $table->unsignedInteger('rankings_added')->default(0);
            $table->unsignedInteger('rankings_updated')->default(0);
            $table->unsignedInteger('backlinks_added')->default(0);
            $table->unsignedInteger('backlinks_deactivated')->default(0);

            // Metadata
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('changelog')->nullable();
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            // Índices
            $table->index('domain_id');
            $table->index('snapshot_date');
            $table->index('status');
            $table->index('created_at');

            // Foreign Keys
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
