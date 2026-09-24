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
        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->id();
            $table->string('agent');
            $table->string('model');
            $table->foreignId('job_posting_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->boolean('cache_hit')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
    }
};
