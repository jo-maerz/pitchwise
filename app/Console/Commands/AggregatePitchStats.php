<?php

namespace App\Console\Commands;

use App\Services\PitchStatsAggregator;
use Illuminate\Console\Command;

class AggregatePitchStats extends Command
{
    protected $signature = 'practice:aggregate-stats';

    protected $description = 'Roll finished practice runs into per-pitch statistics (user_pitch_stats)';

    public function handle(PitchStatsAggregator $aggregator): int
    {
        $count = $aggregator->aggregatePending();
        $this->info("Aggregated {$count} finished session(s).");

        return self::SUCCESS;
    }
}
