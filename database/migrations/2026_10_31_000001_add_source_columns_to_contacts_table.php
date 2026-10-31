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
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('source')->default('alias_probe')->after('company_id');
            $table->foreignId('job_posting_id')->nullable()->after('source')->constrained('job_postings')->cascadeOnDelete();
            $table->string('person_name')->nullable()->after('local_part');
            $table->string('person_title')->nullable()->after('person_name');

            $table->dropUnique(['company_id', 'local_part']);
            $table->index('job_posting_id');
        });

        DB::statement("create unique index contacts_alias_unique on contacts (company_id, local_part) where source = 'alias_probe'");
        DB::statement("create unique index contacts_named_unique on contacts (company_id, email) where source = 'named'");
        DB::statement("create unique index contacts_posting_unique on contacts (job_posting_id, email) where source = 'posting' and job_posting_id is not null");
        DB::statement("create unique index contacts_posting_verdict_unique on contacts (company_id, email) where source = 'posting' and job_posting_id is null");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists contacts_alias_unique');
        DB::statement('drop index if exists contacts_named_unique');
        DB::statement('drop index if exists contacts_posting_unique');
        DB::statement('drop index if exists contacts_posting_verdict_unique');

        DB::table('contacts')->where('source', '!=', 'alias_probe')->delete();

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['job_posting_id']);
            $table->dropConstrainedForeignId('job_posting_id');
            $table->dropColumn(['source', 'person_name', 'person_title']);
            $table->unique(['company_id', 'local_part']);
        });
    }
};
