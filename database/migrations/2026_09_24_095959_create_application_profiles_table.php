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
        Schema::create('application_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('language', 8);
            $table->boolean('is_active')->default(true);
            $table->string('cv_path')->nullable();
            $table->string('cv_original_name')->nullable();
            $table->unsignedInteger('cv_size_bytes')->nullable();
            $table->timestamp('cv_uploaded_at')->nullable();
            $table->string('email_subject', 200);
            $table->text('email_body');
            $table->text('cover_letter')->nullable();
            $table->json('links')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_profiles');
    }
};
