<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $source_id
 * @property int $collection_run_id
 * @property int|null $last_seen_run_id
 * @property int|null $company_id
 * @property string $external_id
 * @property string $title
 * @property string $company_name
 * @property string|null $location
 * @property bool|null $is_remote
 * @property string|null $department
 * @property string|null $employment_type
 * @property string $url
 * @property string|null $apply_url
 * @property string|null $description_html
 * @property string|null $description_text
 * @property CarbonImmutable|null $published_at
 * @property array<string, mixed> $raw
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['source_id', 'collection_run_id', 'last_seen_run_id', 'external_id', 'title', 'company_name', 'location', 'is_remote', 'department', 'employment_type', 'url', 'apply_url', 'description_html', 'description_text', 'published_at', 'raw', 'first_seen_at', 'last_seen_at'])]
class JobPosting extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_remote' => 'boolean',
            'raw' => 'array',
            'published_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * The run that first discovered this posting.
     *
     * @return BelongsTo<CollectionRun, $this>
     */
    public function collectionRun(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class, 'collection_run_id');
    }

    /**
     * @return BelongsTo<CollectionRun, $this>
     */
    public function lastSeenRun(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class, 'last_seen_run_id');
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
