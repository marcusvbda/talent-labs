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
        Schema::create('outreach_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->nullable();
            $table->string('email')->nullable();
            $table->string('reason')->nullable();
            $table->string('source');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        DB::statement('alter table outreach_suppressions add constraint outreach_suppressions_target_check check ((domain is null) <> (email is null))');
        DB::statement('create unique index outreach_suppressions_domain_unique on outreach_suppressions (domain) where domain is not null');
        DB::statement('create unique index outreach_suppressions_email_unique on outreach_suppressions (email) where email is not null');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outreach_suppressions');
    }
};
