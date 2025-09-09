<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DailySummaryWidget;
use App\Filament\Widgets\DailyTasksChart;
use App\Filament\Widgets\MonthlySummaryWidget;
use App\Filament\Widgets\MonthlyTasksChart;
use App\Filament\Widgets\ProjectRatioOverview;
use App\Filament\Widgets\TasksPieChart;
use App\Filament\Widgets\WeeklySpentOverview;
use App\Filament\Widgets\WeeklySummaryWidget;
use App\Filament\Widgets\WeeklyTasksChart;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function getWidgets(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WeeklySpentOverview::class,
            ProjectRatioOverview::class,
            DailyTasksChart::class,
            DailySummaryWidget::class,
            WeeklyTasksChart::class,
            WeeklySummaryWidget::class,
            TasksPieChart::class,
            MonthlySummaryWidget::class
        ];
    }
}
