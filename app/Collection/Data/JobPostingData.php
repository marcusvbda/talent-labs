<?php

namespace App\Collection\Data;

use Carbon\CarbonImmutable;

final readonly class JobPostingData
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $externalId,
        public string $title,
        public string $companyName,
        public ?string $location,
        public ?bool $isRemote,
        public ?string $department,
        public ?string $employmentType,
        public string $url,
        public ?string $applyUrl,
        public ?string $descriptionHtml,
        public ?string $descriptionText,
        public ?CarbonImmutable $publishedAt,
        public array $raw,
        public ?string $companyWebsite = null,
    ) {}
}
