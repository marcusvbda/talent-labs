<?php

namespace App\Console\Commands;

use App\Contacts\Support\OutreachSuppressionList;
use App\Enums\ContactSource;
use App\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ContactsPruneNamed extends Command
{
    /** Days a named contact is kept after its last verification. */
    public const int RETENTION_DAYS = 90;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:prune-named';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete named contacts past their retention or covered by a confirmed suppression';

    public function handle(OutreachSuppressionList $suppressions): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);
        $deleted = 0;

        Contact::query()
            ->with('company:id,domain')
            ->where('source', ContactSource::Named)
            ->chunkById(200, function (Collection $contacts) use ($suppressions, $cutoff, &$deleted): void {
                foreach ($contacts as $contact) {
                    $domain = $contact->company->domain;

                    $expired = $contact->checked_at->lessThan($cutoff);
                    $suppressed = $suppressions->covers($contact->email)
                        || ($domain !== null && $suppressions->coversDomain($domain));

                    // Model delete, so realtime events fire; applications keep their own recipient snapshot.
                    if (($expired || $suppressed) && $contact->delete()) {
                        $deleted++;
                    }
                }
            });

        $this->info("Deleted {$deleted} named contact(s).");

        return self::SUCCESS;
    }
}
