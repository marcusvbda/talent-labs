<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        });

        // Identifier-less (aggregator) sources may repeat per adapter, so
        // uniqueness only applies when an identifier is set.
        DB::statement('CREATE UNIQUE INDEX sources_adapter_identifier_unique ON sources (adapter, identifier) WHERE identifier IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
