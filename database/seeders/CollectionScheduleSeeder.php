<?php

namespace Database\Seeders;

use App\Models\CollectionSchedule;
use Illuminate\Database\Seeder;

class CollectionScheduleSeeder extends Seeder
{
    /**
     * Seed the singleton collection schedule row (idempotent).
     */
    public function run(): void
    {
        CollectionSchedule::unguarded(
            fn () => CollectionSchedule::firstOrCreate(['id' => 1], CollectionSchedule::defaults()),
        );
    }
}
