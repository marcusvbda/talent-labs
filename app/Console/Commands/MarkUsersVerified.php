<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MarkUsersVerified extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:mark-verified {--force : Stamp the listed users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List users without a verified email; --force stamps them';

    public function handle(): int
    {
        $users = User::query()
            ->whereNull('email_verified_at')
            ->orderBy('id')
            ->get(['id', 'email', 'created_at']);

        if ($users->isEmpty()) {
            $this->info('No users without a verified email.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'email', 'created_at'],
            $users->map(fn (User $user): array => [
                $user->id,
                $user->email,
                $user->created_at?->toDateTimeString(),
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->info("Found {$users->count()} users without a verified email. Nothing changed; run with --force to stamp them.");

            return self::SUCCESS;
        }

        $stamped = User::query()
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);

        $this->info("Stamped {$stamped} users as verified.");

        return self::SUCCESS;
    }
}
