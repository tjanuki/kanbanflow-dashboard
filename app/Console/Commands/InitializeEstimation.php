<?php

namespace App\Console\Commands;

use App\Models\Estimate;
use Carbon\Carbon;
use Illuminate\Console\Command;

class InitializeEstimation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:initialize-estimation {YearMonth? : The year and month to initialize the estimation data for.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize the estimation data for the specified year and month.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $yearMonth = $this->argument('YearMonth') ?? now()->format('Y-m');

        $this->info("Initializing estimation data for {$yearMonth}...");

        $startDay = Carbon::parse($yearMonth . '-01');

        // Set 7 hours for weekdays and 0 hours for weekends
        for ($day = $startDay->copy(); $day->lte($startDay->copy()->endOfMonth()); $day->addDay()) {
            if (Estimate::whereDate('date', $day->toDateString())->exists()) {
                continue;
            }

            Estimate::create([
                'date' => $day,
                'estimated_seconds' => $day->isWeekday() ? config('kanbanflow.estimation.base_seconds') : 0,
            ]);
        }

        $this->info('Estimation data initialized.');
    }
}
