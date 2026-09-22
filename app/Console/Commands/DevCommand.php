<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Prohibitable;
use Illuminate\Foundation\DevCommands;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Overrides the framework's default "dev" command to always run processes via
 * `concurrently` instead of `@laravel/multiplex`, since Yarn Classic (1.x) has
 * no `dlx` subcommand and would otherwise fail resolving the multiplex binary.
 */
#[AsCommand(name: 'dev')]
class DevCommand extends Command
{
    use Prohibitable;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev
        {--timestamps : Display timestamps on each output line}
        {--no-restart : Disable auto-restart on crash}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the dev processes';

    public function handle(): int
    {
        if ($this->isProhibited()) {
            return self::FAILURE;
        }

        $devCommands = DevCommands::commands();

        $names = array_column($devCommands, 'name');
        $commands = array_column($devCommands, 'command');
        $colors = array_column($devCommands, 'color');

        $binary = base_path('node_modules/.bin/concurrently');

        $command = sprintf(
            '%s -c %s "%s" --names=%s',
            escapeshellarg($binary),
            escapeshellarg(implode(',', $colors)),
            implode('" "', $commands),
            implode(',', $names),
        );

        if (DevCommands::shouldAutoRestart() && ! $this->option('no-restart')) {
            $command .= ' --restart-tries=5 --restart-after=1000';
        } else {
            $command .= ' --kill-others-on-fail';
        }

        if ($this->option('timestamps') || DevCommands::shouldIncludeTimestamps()) {
            $command .= ' --timestamp-format="HH:mm:ss" -p "{time} [{name}]"';
        }

        foreach ($devCommands as $devCommand) {
            $this->line(sprintf(
                '<fg=%s>[%s]</> %s',
                $devCommand['color'],
                $devCommand['name'],
                $devCommand['command'],
            ));
        }

        $this->line('');

        passthru($command, $exitCode);

        return $exitCode;
    }
}
