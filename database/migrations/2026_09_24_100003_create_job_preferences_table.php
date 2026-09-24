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
            $table->jsonb('keywords')->default(new Expression("'[]'"));
            $table->jsonb('stack')->default(new Expression("'[]'"));
            $table->jsonb('locations')->default(new Expression("'[]'"));
            $table->boolean('accepts_remote')->default(true);
            $table->string('cv_path')->nullable();
            $table->string('cv_original_name')->nullable();
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->boolean('auto_send_enabled')->default(false);
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
