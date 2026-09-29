<p class="text-sm text-gray-500 dark:text-gray-400">
    Generated {{ \Carbon\CarbonImmutable::parse($report['generated_at'])->format('j M Y H:i') }} · last {{ $report['window_days'] }} days
</p>
