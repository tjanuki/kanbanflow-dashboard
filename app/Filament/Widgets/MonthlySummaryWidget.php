<?php

namespace App\Filament\Widgets;

use App\Models\Estimate;
use App\Models\Task;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

class MonthlySummaryWidget extends BaseWidget
{
    protected static ?int $sort = 8;

    public function table(Table $table): Table
    {
        $dailyEstimates = Estimate::query()
            ->selectRaw('estimates.date, SUM(tasks.total_seconds_spent) as total_seconds_spent, MAX(estimates.estimated_seconds) as estimated_seconds')
            ->withDefaultProjects()
            ->whereDate('estimates.date', '<=', today()->endOfMonth())
            ->groupBy('estimates.date')
            ->orderBy('estimates.date', 'desc');

        return $table
            ->query(
                Estimate::query()
                    ->selectRaw("strftime('%Y-%m', tasks.date) as month, SUM(tasks.total_seconds_spent) as total_seconds_spent, SUM(estimates.estimated_seconds) as estimated_seconds")
                    ->joinSub($dailyEstimates, 'tasks', function ($join) {
                        $join->on('estimates.date', '=', 'tasks.date');
                    })
                    ->groupBy('month')
                    ->orderBy('month', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('month')
                    ->label('Month'),
                Tables\Columns\TextColumn::make('estimated_seconds')
                    ->label('Estimated (Hours)')
                    ->formatStateUsing(fn($state) => number_format($state / 3600, 1))
                    ->alignRight(),
                Tables\Columns\TextColumn::make('total_seconds_spent')
                    ->label('Spent (Hours)')
                    ->formatStateUsing(fn($state) => number_format($state / 3600, 1))
                    ->alignRight(),
                Tables\Columns\TextColumn::make('difference')
                    ->label('Diff (Hours)')
                    ->getStateUsing(fn($record
                    ) => number_format(($record->total_seconds_spent - $record->estimated_seconds) / 3600, 1))
                    ->alignRight(),
            ])->paginated(false);
    }

    public function getTableRecordKey(Model $record): string
    {
        return $record->month ?? 'unknown';
    }
}
