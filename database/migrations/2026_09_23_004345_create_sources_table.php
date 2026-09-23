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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('adapter');
            $table->string('identifier')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('interval_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
            $table->timestamps();

            // NULLS NOT DISTINCT (PostgreSQL 15+) so a null identifier (Remotive)
            // still counts toward uniqueness per adapter.
            $table->unique(['adapter', 'identifier'])->nullsNotDistinct();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
