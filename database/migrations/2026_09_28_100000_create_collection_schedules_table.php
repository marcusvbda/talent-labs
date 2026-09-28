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
        Schema::create('collection_schedules', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->jsonb('times');
            $table->string('timezone', 64);
            $table->string('last_slot_key', 32)->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->string('last_result', 200)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_schedules');
    }
};
