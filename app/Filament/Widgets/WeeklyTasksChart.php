<?php

namespace App\Filament\Widgets;

use App\Models\Estimate;
use Filament\Widgets\ChartWidget;

class WeeklyTasksChart extends ChartWidget
{
    protected static ?int $sort = 5;
    protected static ?string $heading = 'Weekly Task Summary';

    protected function getData(): array
    {
        $weekStartExpression = Estimate::query()->getConnection()->getDriverName() === 'sqlite'
            ? "DATE(tasks.date, '-' || ((CAST(strftime('%w', tasks.date) AS INTEGER) + 6) % 7) || ' days')"
            : 'DATE(DATE_SUB(tasks.date, INTERVAL (DAYOFWEEK(tasks.date) - 2 + 7) % 7 DAY))';

        $dailyEstimates = Estimate::query()
            ->selectRaw('estimates.date, SUM(tasks.total_seconds_spent) as total_seconds_spent, MAX(estimates.estimated_seconds) as estimated_seconds')
            ->withDefaultProjects()
            ->whereBetween('estimates.date', [today()->startOfMonth(), today()->endOfMonth()])
            ->groupBy('estimates.date')
            ->orderBy('estimates.date');

        // show monthly tasks summary by weekly
        $data = Estimate::query()
            ->selectRaw("
                {$weekStartExpression} as week,
                SUM(tasks.total_seconds_spent) as total_seconds_spent,
                SUM(estimates.estimated_seconds) as estimated_seconds
            ")
            ->joinSub($dailyEstimates, 'tasks', function ($join) {
                $join->on('estimates.date', '=', 'tasks.date');
            })
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        return [
            'labels' => $data->pluck('week'),
            'datasets' => [
                [
                    'label' => 'Estimated (Hours)',
                    'data' => $data->pluck('estimated_seconds')->map(fn ($state) => $state / 3600),
                    'backgroundColor' => '#38c172',
                ],
                [
                    'label' => 'Spent (Hours)',
                    'data' => $data->pluck('total_seconds_spent')->map(fn ($state) => $state / 3600),
                    'backgroundColor' => '#3490dc',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
