<?php

namespace App\Filament\Widgets;

use App\Models\Estimate;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WeeklySpentOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // estimate - spent
        $todayEstimate = Estimate::query()
            ->whereDate('date', today())
            ->sum('estimated_seconds');
        $todaySpent = Task::query()
            ->withDefaultProjects()
            ->whereDate('date', today())
            ->sum('total_seconds_spent');
        $todayTimeLeft =  number_format(($todayEstimate - $todaySpent) / 3600, 1);

        $weekEstimate = Estimate::query()
            ->whereBetween('date', [today()->startOfWeek(), today()->endOfWeek()])
            ->sum('estimated_seconds');
        $weekSpent = Task::query()
            ->withDefaultProjects()
            ->whereBetween('date', [today()->startOfWeek(), today()->endOfWeek()])
            ->sum('total_seconds_spent');
        $weekTimeLeft = number_format(($weekEstimate - $weekSpent) / 3600, 1);

        $monthEstimate = Estimate::query()
            ->whereBetween('date', [today()->startOfMonth(), today()->endOfMonth()])
            ->sum('estimated_seconds');
        $monthSpent = Task::query()
            ->withDefaultProjects()
            ->whereBetween('date', [today()->startOfMonth(), today()->endOfMonth()])
            ->sum('total_seconds_spent');
        $monthTimeLeft = number_format(($monthEstimate - $monthSpent) / 3600, 1);

        return [
            Stat::make('Time left for today', $todayTimeLeft),
            Stat::make('Time left for this week', $weekTimeLeft),
            Stat::make('Time left for this month', $monthTimeLeft),
        ];
    }
}
