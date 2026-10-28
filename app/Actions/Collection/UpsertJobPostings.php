<?php

namespace App\Actions\Collection;

use App\Models\JobPosting;
use Illuminate\Support\Facades\DB;

class UpsertJobPostings
{
    private const CHUNK_SIZE = 200;

    /**
     * Columns refreshed when a posting already exists. Never `collection_run_id`,
     * `first_seen_at`, `created_at` or `company_id`: those belong to the run that first stored it.
     * `source_contacts` is merged separately in the upsert so a run without contacts keeps them.
     */
    private const MUTABLE_COLUMNS = [
        'title', 'company_name', 'location', 'is_remote', 'department', 'employment_type', 'role_family',
        'ineligible_reason', 'restrictions', 'url', 'apply_url', 'company_website', 'description_html', 'description_text', 'published_at', 'raw',
        'last_seen_run_id', 'last_seen_at', 'updated_at',
    ];

    /**
     * Insert or refresh postings by (source_id, external_id). Query-builder write: no model events.
     *
     * @param  list<array<string, mixed>>  $rows  rows already in column form (json columns encoded)
     */
    public function handle(array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            JobPosting::upsert($chunk, ['source_id', 'external_id'], [
                ...self::MUTABLE_COLUMNS,
                // A run that brings no contacts must not wipe the ones already stored.
                'source_contacts' => DB::raw('coalesce(excluded.source_contacts, job_postings.source_contacts)'),
            ]);
        }
    }
}
