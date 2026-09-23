<?php

namespace App\Models;

use App\Enums\ContactStatus;
use App\Enums\DomainStatus;
use App\Models\Concerns\BroadcastsRealtime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $domain
 * @property DomainStatus $domain_status
 * @property CarbonImmutable|null $domain_checked_at
 * @property ContactStatus $contact_status
 * @property CarbonImmutable|null $contact_checked_at
 * @property bool|null $is_catch_all
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Contact> $contacts
 * @property-read Collection<int, JobPosting> $jobPostings
 */
#[Fillable(['name', 'normalized_name', 'domain', 'domain_status', 'domain_checked_at', 'contact_status', 'contact_checked_at', 'is_catch_all'])]
class Company extends Model
{
    use BroadcastsRealtime;

    protected static function booted(): void
    {
        static::saved(function (Company $company): void {
            static::broadcastUpdated($company);
        });

        static::deleted(function (Company $company): void {
            static::broadcastUpdated($company);
        });
    }

    protected static function broadcastUpdated(Company $company): void
    {
        static::broadcastRealtime('companies', 'CompanyUpdated', ['id' => $company->id]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'domain_status' => DomainStatus::class,
            'contact_status' => ContactStatus::class,
            'is_catch_all' => 'boolean',
            'domain_checked_at' => 'datetime',
            'contact_checked_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @return HasMany<JobPosting, $this>
     */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class, 'company_id');
    }
}
