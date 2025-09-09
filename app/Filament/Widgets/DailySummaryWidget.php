<?php

namespace App\Filament\Widgets;

use App\Models\Estimate;
use App\Models\Task;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

class DailySummaryWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Estimate::query()
                    ->selectRaw('estimates.date, SUM(tasks.total_seconds_spent) as total_seconds_spent, MAX(estimates.estimated_seconds) as estimated_seconds')
                    ->withDefaultProjects()
                    ->whereDate('estimates.date', '>=', today()->startOfWeek())
                    ->whereDate('estimates.date', '<=', today()->endOfWeek())
                    ->groupBy('estimates.date')
                    ->orderBy('estimates.date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->formatStateUsing(function ($state) {
                        return Carbon::parse($state)->format('Y-m-d [D]');
                    })
                    ->label('Date'),
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
                    ->getStateUsing(fn ($record
                    ) => number_format(($record->total_seconds_spent - $record->estimated_seconds) / 3600, 1))
                    ->alignRight(),
            ])->paginated(false);
    }

    public function getTableRecordKey(Model $record): string
    {
        return $record->date ?? 'unknown';
    }
}
