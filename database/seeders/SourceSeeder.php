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
            ['name' => 'Stripe', 'adapter' => SourceAdapter::Greenhouse, 'identifier' => 'stripe', 'settings' => null],
            ['name' => 'Palantir', 'adapter' => SourceAdapter::Lever, 'identifier' => 'palantir', 'settings' => null],
            ['name' => 'Linear', 'adapter' => SourceAdapter::Ashby, 'identifier' => 'linear', 'settings' => null],
            ['name' => 'Remotive', 'adapter' => SourceAdapter::Remotive, 'identifier' => null, 'settings' => ['limit' => 100]],
        ];

        foreach ($sources as $source) {
            Source::updateOrCreate(
                ['adapter' => $source['adapter'], 'identifier' => $source['identifier']],
                ['name' => $source['name'], 'settings' => $source['settings'], 'is_active' => true],
            );
        }
    }
}
