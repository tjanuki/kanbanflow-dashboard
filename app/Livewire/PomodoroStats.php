<?php

namespace App\Livewire;

use App\Models\TimeEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Pomodoro Statistics" — a top-bar chart icon that opens a full-screen modal
 * with a bar chart of completed pomodoros over time, filterable by period and
 * grouping (day/week/month).
 */
class PomodoroStats extends Component
{
    public bool $showStats = false;

    /** Date window: last_7_days | last_30_days | last_90_days | this_month | this_year */
    public string $period = 'last_30_days';

    /** Bucket size: day | week | month */
    public string $groupBy = 'day';

    /** Opened from the top-bar button ({@see PomodoroStatsButton}). */
    #[On('open-pomodoro-stats')]
    public function open(): void
    {
        $this->showStats = true;
    }

    public function close(): void
    {
        $this->showStats = false;
    }

    /** Inclusive [start, end] of the selected period, both at day boundaries. */
    private function range(): array
    {
        $end = today();

        $start = match ($this->period) {
            'last_7_days' => today()->subDays(6),
            'last_90_days' => today()->subDays(89),
            'this_month' => today()->startOfMonth(),
            'this_year' => today()->startOfYear(),
            default => today()->subDays(29), // last_30_days
        };

        return [$start, $end];
    }

    /**
     * Everything the chart view needs: ordered buckets (label, count, bar
     * height %), y-axis gridlines and the axis ceiling.
     *
     * @return array{buckets: array<int, array{label: string, count: int, heightPct: float, showLabel: bool, tipStart: string, tipEnd: string, flip: bool}>, gridlines: array<int, array{value: int, pct: float}>, niceMax: int, total: int}
     */
    #[Computed]
    public function chart(): array
    {
        [$start, $end] = $this->range();

        $entries = TimeEntry::query()
            ->where('type', 'pomodoro')
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get(['id', 'started_at']);

        // Pre-seed every bucket across the range so empty days render as gaps,
        // then tally each entry into its bucket.
        $buckets = [];

        // Each bucket carries a hover-tooltip range: a single day/month, or a
        // "Monday 6 July → Sunday 12 July" span for weeks (tipEnd is empty when
        // the range is a single point).
        if ($this->groupBy === 'month') {
            for ($c = $start->copy()->startOfMonth(), $last = $end->copy()->startOfMonth(); $c <= $last; $c->addMonth()) {
                $buckets[$c->format('Y-m')] = ['label' => $c->format('M Y'), 'count' => 0, 'tipStart' => $c->format('F Y'), 'tipEnd' => ''];
            }
            $keyFor = fn (Carbon $d) => $d->format('Y-m');
        } elseif ($this->groupBy === 'week') {
            for ($c = $start->copy()->startOfWeek(), $last = $end->copy()->startOfWeek(); $c <= $last; $c->addWeek()) {
                $buckets[$c->toDateString()] = ['label' => $c->format('M j'), 'count' => 0, 'tipStart' => $c->format('l j F'), 'tipEnd' => $c->copy()->endOfWeek()->format('l j F')];
            }
            $keyFor = fn (Carbon $d) => $d->copy()->startOfWeek()->toDateString();
        } else {
            for ($c = $start->copy(), $last = $end->copy(); $c <= $last; $c->addDay()) {
                $buckets[$c->toDateString()] = ['label' => $c->format('M j'), 'count' => 0, 'tipStart' => $c->format('l j F'), 'tipEnd' => ''];
            }
            $keyFor = fn (Carbon $d) => $d->toDateString();
        }

        foreach ($entries as $entry) {
            $key = $keyFor($entry->started_at);

            if (isset($buckets[$key])) {
                $buckets[$key]['count']++;
            }
        }

        $counts = array_column($buckets, 'count');
        $max = $counts ? max($counts) : 0;
        [$niceMax, $step] = $this->niceScale($max);

        // Thin day labels so a 30-bucket axis stays readable; week/month show all.
        $count = count($buckets);
        $labelEvery = $this->groupBy === 'day' ? max(1, (int) ceil($count / 12)) : 1;

        $out = [];
        $i = 0;
        foreach ($buckets as $bucket) {
            $out[] = [
                'label' => $bucket['label'],
                'count' => $bucket['count'],
                'heightPct' => $niceMax > 0 ? round($bucket['count'] / $niceMax * 100, 2) : 0,
                'showLabel' => $i % $labelEvery === 0 || $i === $count - 1,
                'tipStart' => $bucket['tipStart'],
                'tipEnd' => $bucket['tipEnd'],
                // Open the hover dialog toward the centre for right-side bars so
                // it doesn't spill past the chart's right edge.
                'flip' => $count > 1 && ($i + 0.5) / $count > 0.6,
            ];
            $i++;
        }

        $gridlines = [];
        for ($v = 0; $v <= $niceMax; $v += $step) {
            $gridlines[] = ['value' => (int) $v, 'pct' => $niceMax > 0 ? round($v / $niceMax * 100, 2) : 0];
        }

        return [
            'buckets' => $out,
            'gridlines' => $gridlines,
            'niceMax' => (int) $niceMax,
            'total' => (int) array_sum($counts),
        ];
    }

    /**
     * Round a max value up to a friendly axis ceiling and step (1/2/5 × 10ⁿ)
     * aiming for ~5 gridlines. Returns [niceMax, step].
     */
    private function niceScale(int $max, int $ticks = 5): array
    {
        if ($max <= 0) {
            return [5, 1];
        }

        $rawStep = $max / $ticks;
        $mag = pow(10, floor(log10($rawStep)));
        $norm = $rawStep / $mag;

        $step = ($norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 5 ? 5 : 10))) * $mag;
        $niceMax = ceil($max / $step) * $step;

        return [(int) $niceMax, (int) $step];
    }

    /** Download the current chart's buckets as a CSV. */
    public function export(): StreamedResponse
    {
        $chart = $this->chart();
        $filename = 'pomodoro-statistics-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($chart) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Period', 'Pomodoros']);

            foreach ($chart['buckets'] as $bucket) {
                fputcsv($out, [$bucket['label'], $bucket['count']]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render(): View
    {
        return view('livewire.pomodoro-stats');
    }
}
