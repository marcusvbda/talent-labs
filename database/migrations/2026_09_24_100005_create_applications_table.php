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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('application_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('body');
            $table->string('origin');
            $table->string('language', 8)->nullable();
            $table->string('status');
            $table->string('stage', 32)->nullable();
            $table->string('sub_step', 32)->nullable();
            $table->jsonb('stage_log')->default(new Expression("'[]'::jsonb"));
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'company_id']);
            $table->index(['user_id', 'queued_at']);
            $table->index('status');
            $table->index(['user_id', 'status', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
