<?php

namespace Database\Seeders;

use App\Enums\SourceAdapter;
use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Seed the starter job sources (idempotent).
     */
    public function run(): void
    {
        $sources = [
            ['name' => 'Remotive', 'adapter' => SourceAdapter::Remotive, 'identifier' => null, 'settings' => ['limit' => 100]],
            ['name' => 'RemoteOK', 'adapter' => SourceAdapter::RemoteOk, 'identifier' => null, 'settings' => null],
            ['name' => 'Arbeitnow', 'adapter' => SourceAdapter::Arbeitnow, 'identifier' => null, 'settings' => null],
            ['name' => 'Jobicy', 'adapter' => SourceAdapter::Jobicy, 'identifier' => null, 'settings' => ['count' => 50]],
        ];

        foreach ($sources as $source) {
            Source::firstOrCreate(
                ['adapter' => $source['adapter'], 'identifier' => $source['identifier']],
                ['name' => $source['name'], 'settings' => $source['settings'], 'is_active' => true],
            );
        }
    }
}
