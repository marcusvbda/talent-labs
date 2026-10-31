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
        Schema::table('applications', function (Blueprint $table) {
            $table->string('recipient_tier', 1)->nullable()->after('recipient_email');
            $table->string('recipient_source', 32)->nullable()->after('recipient_tier');

            $table->index(['recipient_email', 'queued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['recipient_email', 'queued_at']);
            $table->dropColumn(['recipient_tier', 'recipient_source']);
        });
    }
};
