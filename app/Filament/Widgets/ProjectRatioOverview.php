<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

class ProjectRatioOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $targetProjects = Project::where('is_default', true)->get();
        $dailyTasks = $targetProjects->mapWithKeys(function ($project) {
            return [
                $project->name => number_format($project->tasks()->whereDate('date',
                        today())->sum('total_seconds_spent') / 3600, 1)
            ];
        });
        $weeklyTasks = $targetProjects->mapWithKeys(function ($project) {
            return [
                $project->name => number_format($project->tasks()->whereBetween('date',
                        [today()->startOfWeek(), today()->endOfWeek()])->sum('total_seconds_spent') / 3600, 1)
            ];
        });
        $monthlyTasks = $targetProjects->mapWithKeys(function ($project) {
            return [
                $project->name => number_format($project->tasks()->whereBetween('date',
                        [today()->startOfMonth(), today()->endOfMonth()])->sum('total_seconds_spent') / 3600, 1)
            ];
        });

        $dailyRatio = $this->getRatio($dailyTasks);
        $weeklyRatio = $this->getRatio($weeklyTasks);
        $monthlyRatio = $this->getRatio($monthlyTasks);

        return [
            Stat::make('Daily Ratio', $dailyRatio->map(fn($ratio) => $ratio)->join(' - '))
                ->chart($this->insertZeros($dailyRatio))
                ->description($dailyRatio->keys()->join(' - '))
                ->color('success'),
            Stat::make('Weekly Ratio', $weeklyRatio->map(fn($ratio) => $ratio)->join(' - '))
                ->chart($this->insertZeros($weeklyRatio))
                ->description($dailyRatio->keys()->join(' - '))
                ->color('success'),
            Stat::make('Monthly Ratio', $monthlyRatio->map(fn($ratio) => $ratio)->join(' - '))
                ->chart($this->insertZeros($monthlyRatio))
                ->description($dailyRatio->keys()->join(' - '))
                ->color('success'),
        ];
    }

    private function getRatio(Collection $tasks): Collection
    {
        $totalSum = $tasks->sum() ?: 1;
        return $tasks->map(function ($value) use ($totalSum) {
            return number_format($value / $totalSum * 100, 0);
        });
    }

    private function insertZeros(Collection $collection): array
    {
        $values = $collection->values();
        return $values->flatMap(function ($value, $index) use ($values) {
            return $index % 2 === 0 ? [$value * 100, 0] : [$value * 100];
        })->toArray();
    }

}
