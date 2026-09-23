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
        Schema::create('source_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_run_id')->constrained('collection_runs')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('jobs_fetched')->default(0);
            $table->unsignedInteger('jobs_new')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['collection_run_id', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_runs');
    }
};
