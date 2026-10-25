<?php

declare(strict_types=1);

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
        // One row per uploaded catalog file. The import runs on the queue, so
        // this is where the admin panel reads progress and row errors from.
        Schema::create('catalog_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            // Relative to the local disk; the file is kept for troubleshooting.
            $table->string('file_path');
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            // Per-row problems: a capped list of {position, sku, messages}.
            $table->json('errors')->nullable();
            // Why the whole file was rejected (unreadable JSON, wrong schema
            // version), as opposed to a single bad row.
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // The newest runs first, and "is one running?" checks.
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_imports');
    }
};
