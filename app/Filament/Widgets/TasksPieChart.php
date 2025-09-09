<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Widgets\ChartWidget;

class TasksPieChart extends ChartWidget
{
    protected static ?string $heading = 'Project Task Summary';

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {

        // gather monthly tasks summary by project name
        $data = Task::query()
            ->selectRaw('projects.name as project_name, SUM(total_seconds_spent) as total_seconds_spent')
            ->join('projects', 'tasks.color', '=', 'projects.color')
            ->whereDate('date', '>=', today()->startOfMonth())
            ->groupBy('projects.name')
            ->orderBy('total_seconds_spent', 'desc')
            ->get();

        return [
            'labels' => $data->pluck('project_name'),
            'datasets' => [
                [
                    'data' => $data->pluck('total_seconds_spent')->map(fn($state) => number_format($state / 3600, 1)),
                    'backgroundColor' => ['#dbffff', '#c7f7ff', '#ffffe0', '#3490dc', '#38c172', '#f6993f', '#e3342f', '#6cb2eb']
                ]
            ]
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
