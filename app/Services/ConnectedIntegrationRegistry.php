<?php

namespace App\Services;

use App\Contracts\IntegrationPlugin;
use App\Contracts\OAuthIntegrationPlugin;
use InvalidArgumentException;

class ConnectedIntegrationRegistry
{
    /** @var array<string, IntegrationPlugin> */
    private array $plugins = [];

    /** @param iterable<IntegrationPlugin> $plugins */
    public function __construct(iterable $plugins)
    {
        foreach ($plugins as $plugin) {
            $this->plugins[$plugin->key()] = $plugin;
        }
    }

    public function get(string $key): IntegrationPlugin
    {
        return $this->plugins[$key]
            ?? throw new InvalidArgumentException("Unknown connected integration plugin [{$key}].");
    }

    public function oauth(string $key): OAuthIntegrationPlugin
    {
        $plugin = $this->get($key);

        if (! $plugin instanceof OAuthIntegrationPlugin) {
            throw new InvalidArgumentException("Connected integration plugin [{$key}] does not use OAuth.");
        }

        return $plugin;
    }

    /**
     * Keys of the registered plugins that can send email.
     *
     * @return list<string>
     */
    public function sendingPluginKeys(): array
    {
        return array_keys(array_filter(
            $this->plugins,
            fn (IntegrationPlugin $plugin): bool => in_array('email.send', $plugin->capabilities(), true),
        ));
    }

    /** @return list<array{key: string, label: string, description: string, category: string, icon: string, capabilities: list<string>}> */
    public function metadata(): array
    {
        return array_values(array_map(
            fn (IntegrationPlugin $plugin): array => [
                'key' => $plugin->key(),
                'label' => $plugin->label(),
                'description' => $plugin->description(),
                'category' => $plugin->category(),
                'icon' => $plugin->icon(),
                'capabilities' => $plugin->capabilities(),
            ],
            $this->plugins,
        ));
    }
}
