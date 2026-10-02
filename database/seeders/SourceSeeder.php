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
            ['name' => 'Adzuna', 'adapter' => SourceAdapter::Adzuna, 'identifier' => null, 'settings' => ['country' => 'br', 'queries' => 'desenvolvedor,developer,backend,frontend,full stack,suporte,customer success,product manager', 'pages' => 1], 'is_active' => false],
            ['name' => 'Y Combinator', 'adapter' => SourceAdapter::YCombinator, 'identifier' => null, 'settings' => ['roles' => 'software-engineer,product-manager,support'], 'is_active' => false],

            // Curated ATS boards (Brazil-targeted), seeded inactive for the owner to enable.
            ['name' => 'Wellhub', 'adapter' => SourceAdapter::Greenhouse, 'identifier' => 'gympass', 'settings' => ['locations' => 'Brazil, São Paulo, Sao Paulo', 'website' => 'https://wellhub.com'], 'is_active' => false],
            ['name' => 'QuintoAndar', 'adapter' => SourceAdapter::Greenhouse, 'identifier' => 'quintoandar', 'settings' => ['locations' => 'Brasil, Brazil, São Paulo, Sao Paulo', 'website' => 'https://www.quintoandar.com.br'], 'is_active' => false],
            ['name' => 'VTEX', 'adapter' => SourceAdapter::Greenhouse, 'identifier' => 'vtex', 'settings' => ['locations' => 'Brazil, São Paulo, Sao Paulo, Rio de Janeiro', 'website' => 'https://vtex.com'], 'is_active' => false],
            ['name' => 'EBANX', 'adapter' => SourceAdapter::Greenhouse, 'identifier' => 'ebanx', 'settings' => ['locations' => 'Curitiba, São Paulo, Sao Paulo', 'website' => 'https://www.ebanx.com'], 'is_active' => false],
            ['name' => 'Brex', 'adapter' => SourceAdapter::Greenhouse, 'identifier' => 'brex', 'settings' => ['locations' => 'São Paulo, Sao Paulo, Brazil', 'website' => 'https://www.brex.com'], 'is_active' => false],
            ['name' => 'Coinbase', 'adapter' => SourceAdapter::Greenhouse, 'identifier' => 'coinbase', 'settings' => ['locations' => 'Remote - Brazil', 'website' => 'https://www.coinbase.com'], 'is_active' => false],
            ['name' => 'Swile', 'adapter' => SourceAdapter::Lever, 'identifier' => 'swile', 'settings' => ['locations' => 'Brasil, São Paulo, Sao Paulo', 'website' => 'https://www.swile.co'], 'is_active' => false],
            ['name' => 'dLocal', 'adapter' => SourceAdapter::Lever, 'identifier' => 'dlocal', 'settings' => ['locations' => 'Sao Paulo, São Paulo', 'website' => 'https://dlocal.com'], 'is_active' => false],
            ['name' => 'OpenAI', 'adapter' => SourceAdapter::Ashby, 'identifier' => 'openai', 'settings' => ['locations' => 'São Paulo, Sao Paulo', 'website' => 'https://openai.com'], 'is_active' => false],
        ];

        foreach ($sources as $source) {
            Source::firstOrCreate(
                ['adapter' => $source['adapter'], 'identifier' => $source['identifier']],
                ['name' => $source['name'], 'settings' => $source['settings'], 'is_active' => $source['is_active'] ?? true],
            );
        }
    }
}
