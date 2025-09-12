<?php

namespace App\Filament\Widgets;

use App\Models\Estimate;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

class WeeklySummaryWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    public function table(Table $table): Table
    {
        $dailyEstimates = Estimate::query()
            ->selectRaw('estimates.date, SUM(tasks.total_seconds_spent) as total_seconds_spent, MAX(estimates.estimated_seconds) as estimated_seconds')
            ->withDefaultProjects()
            ->whereBetween('estimates.date', [today()->startOfMonth(), today()->endOfMonth()])
            ->groupBy('estimates.date')
            ->orderBy('estimates.date', 'desc');

        return $table
            ->query(
                Estimate::query()
                    ->selectRaw("
                        DATE(DATE_SUB(tasks.date, INTERVAL (DAYOFWEEK(tasks.date) - 2 + 7) % 7 DAY)) as week_start,
                        SUM(tasks.total_seconds_spent) as total_seconds_spent,
                        SUM(estimates.estimated_seconds) as estimated_seconds
                    ")
                    ->joinSub($dailyEstimates, 'tasks', function ($join) {
                        $join->on('estimates.date', '=', 'tasks.date');
                    })
                    ->groupBy('week_start')
                    ->orderBy('week_start')
            )->columns([
                Tables\Columns\TextColumn::make('week_start')
                    ->label('Week Starting')
                    ->formatStateUsing(fn ($state) => date('Y-m-d', strtotime($state)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_seconds')
                    ->label('Estimated (Hours)')
                    ->formatStateUsing(fn ($state) => number_format($state / 3600, 1))
                    ->alignRight(),
                Tables\Columns\TextColumn::make('total_seconds_spent')
                    ->label('Spent (Hours)')
                    ->formatStateUsing(fn ($state) => number_format($state / 3600, 1))
                    ->alignRight(),
                Tables\Columns\TextColumn::make('difference')
                    ->label('Diff (Hours)')
                    ->getStateUsing(fn ($record) =>
                    number_format(($record->total_seconds_spent - $record->estimated_seconds) / 3600, 1)
                    )
                    ->alignRight(),
            ])->paginated(false);
    }

    public function getTableRecordKey(Model $record): string
    {
        return $record->week_start ?? 'unknown';
    }
}
