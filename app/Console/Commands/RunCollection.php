<?php

namespace App\Console\Commands;

use App\Actions\Collection\StartCollectionRun;
use App\Exceptions\CollectionRunException;
use Illuminate\Console\Command;

class RunCollection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Start a collection run now (ignores the schedule's pause)";

    public function handle(StartCollectionRun $startCollectionRun): int
    {
        try {
            $run = $startCollectionRun->handle(null);
        } catch (CollectionRunException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Started run #{$run->id}");

        return self::SUCCESS;
    }
}
