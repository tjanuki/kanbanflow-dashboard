<?php

namespace App\Filament\Widgets;

use App\Models\Estimate;
use Filament\Widgets\ChartWidget;

class DailyTasksChart extends ChartWidget
{
    protected static ?string $heading = 'Daily Task Summary';

    protected function getData(): array
    {
        $data = Estimate::query()
            ->selectRaw('estimates.date, SUM(tasks.total_seconds_spent) as total_seconds_spent, MAX(estimates.estimated_seconds) as estimated_seconds')
            ->withDefaultProjects()
            ->whereDate('estimates.date', '>=', today()->startOfWeek())
            ->whereDate('estimates.date', '<=', today()->endOfWeek())
            ->groupBy('estimates.date')
            ->orderBy('estimates.date');

        return [
            'labels' => $data->pluck('date'),
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
