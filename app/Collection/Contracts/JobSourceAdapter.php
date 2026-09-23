<?php

namespace App\Collection\Contracts;

use App\Collection\Data\JobPostingData;
use App\Models\Source;

interface JobSourceAdapter
{
    /**
     * Fetch the current postings published by the given source.
     *
     * @return iterable<JobPostingData>
     */
    public function fetch(Source $source): iterable;
}
