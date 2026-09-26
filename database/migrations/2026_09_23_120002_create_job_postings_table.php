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
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->restrictOnDelete();
            // The run that first discovered this posting.
            $table->foreignId('collection_run_id')->constrained('collection_runs')->restrictOnDelete();
            $table->foreignId('last_seen_run_id')->nullable()->constrained('collection_runs')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('external_id');
            $table->string('title');
            $table->string('company_name');
            $table->string('location')->nullable();
            $table->boolean('is_remote')->nullable();
            $table->string('department')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('role_family')->nullable()->index();
            $table->text('url');
            $table->text('apply_url')->nullable();
            $table->text('company_website')->nullable();
            $table->longText('description_html')->nullable();
            $table->longText('description_text')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('raw');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index('collection_run_id');
            $table->index('published_at');
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
