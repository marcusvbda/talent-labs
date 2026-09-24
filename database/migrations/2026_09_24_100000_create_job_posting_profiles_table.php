<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_posting_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('schema_version');
            $table->string('normalized_title')->nullable();
            $table->string('seniority')->nullable();
            $table->jsonb('stack')->default(new Expression("'[]'"));
            $table->jsonb('locations')->default(new Expression("'[]'"));
            $table->boolean('is_remote')->nullable();
            $table->string('summary', 300)->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();

            $table->index('stack', 'job_posting_profiles_stack_gin', 'gin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_posting_profiles');
    }
};
