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
        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('job_posting_id')->constrained()->nullOnDelete();
            $table->index(['user_id', 'created_at']);
        });

        // A cached cover letter is personal data: it disappears with the account.
        Schema::table('ai_agent_response_caches', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_agent_response_caches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
