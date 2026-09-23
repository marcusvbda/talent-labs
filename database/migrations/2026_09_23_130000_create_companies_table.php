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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->string('domain')->nullable();
            $table->string('domain_status')->default('pending');
            $table->timestamp('domain_checked_at')->nullable();
            $table->string('contact_status')->default('pending');
            $table->timestamp('contact_checked_at')->nullable();
            $table->boolean('is_catch_all')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
