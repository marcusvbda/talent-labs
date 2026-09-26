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
            ['name' => 'RemoteOK', 'adapter' => SourceAdapter::RemoteOk, 'identifier' => null, 'settings' => ['tags' => 'backend,frontend,full stack,php,javascript,react,node,devops,customer support,product']],
            ['name' => 'Arbeitnow', 'adapter' => SourceAdapter::Arbeitnow, 'identifier' => null, 'settings' => ['pages' => 3]],
            ['name' => 'Jobicy', 'adapter' => SourceAdapter::Jobicy, 'identifier' => null, 'settings' => ['count' => 100, 'industries' => 'engineering,technical-support,supporting,qa-testing,web-app-design']],
            ['name' => 'Himalayas', 'adapter' => SourceAdapter::Himalayas, 'identifier' => null, 'settings' => ['queries' => 'backend developer,frontend developer,full stack developer,software engineer,customer support,product manager', 'pages' => 1]],
            ['name' => 'We Work Remotely', 'adapter' => SourceAdapter::WeWorkRemotely, 'identifier' => null, 'settings' => ['categories' => 'remote-programming-jobs,remote-full-stack-programming-jobs,remote-back-end-programming-jobs,remote-front-end-programming-jobs,remote-customer-support-jobs,remote-product-jobs,remote-devops-sysadmin-jobs']],
            ['name' => 'Working Nomads', 'adapter' => SourceAdapter::WorkingNomads, 'identifier' => null, 'settings' => ['categories' => 'Development,Customer Success']],
            ['name' => 'Hacker News', 'adapter' => SourceAdapter::HackerNews, 'identifier' => null, 'settings' => null],
        ];

        foreach ($sources as $source) {
            Source::firstOrCreate(
                ['adapter' => $source['adapter'], 'identifier' => $source['identifier']],
                ['name' => $source['name'], 'settings' => $source['settings'], 'is_active' => true],
            );
        }
    }
}
