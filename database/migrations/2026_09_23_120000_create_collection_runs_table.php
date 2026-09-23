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
        Schema::create('collection_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('batch_id')->nullable();
            $table->unsignedInteger('sources_total')->default(0);
            $table->unsignedInteger('sources_succeeded')->default(0);
            $table->unsignedInteger('sources_failed')->default(0);
            $table->unsignedInteger('jobs_fetched')->default(0);
            $table->unsignedInteger('jobs_new')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_runs');
    }
};
