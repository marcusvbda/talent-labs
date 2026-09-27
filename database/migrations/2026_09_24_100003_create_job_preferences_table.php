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
        Schema::create('job_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('titles')->default(new Expression("'[]'"));
            $table->jsonb('seniorities')->default(new Expression("'[]'"));
            $table->jsonb('stack')->default(new Expression("'[]'"));
            $table->jsonb('locations')->default(new Expression("'[]'"));
            $table->string('remote_mode')->default('remote_or_locations');
            $table->jsonb('exclude_words')->default(new Expression("'[]'"));
            $table->timestamp('saved_at')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cv_original_name')->nullable();
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_preferences');
    }
};
