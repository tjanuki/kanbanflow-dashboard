<?php

namespace App\Filament\Widgets;

use App\Models\Estimate;
use App\Models\Task;
use Filament\Widgets\ChartWidget;

class MonthlyTasksChart extends ChartWidget
{
    protected static bool $isDiscovered = false;
    protected static ?int $sort = 99;
    protected static ?string $heading = 'Monthly Task Summary';

    protected function getData(): array
    {
        $dailyEstimates = Estimate::query()
            ->selectRaw('estimates.date, SUM(tasks.total_seconds_spent) as total_seconds_spent, MAX(estimates.estimated_seconds) as estimated_seconds')
            ->withDefaultProjects()
            ->whereDate('estimates.date', '>=', today()->startOfMonth())
            ->groupBy('estimates.date')
            ->orderBy('estimates.date');

        // show monthly tasks summary by weekly
        $data = Estimate::query()
            ->selectRaw("strftime('%Y-%m', tasks.date) as month, SUM(tasks.total_seconds_spent) as total_seconds_spent, SUM(estimates.estimated_seconds) as estimated_seconds")
            ->leftJoinSub($dailyEstimates, 'tasks', function ($join) {
                $join->on('estimates.date', '=', 'tasks.date');
            })
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get();

        return [
            'labels' => $data->pluck('month'),
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
            ]
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
