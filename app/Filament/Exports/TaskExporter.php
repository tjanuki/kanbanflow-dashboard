<?php

namespace App\Filament\Exports;

use App\Models\Task;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TaskExporter extends Exporter
{
    protected static ?string $model = Task::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('date'),
            ExportColumn::make('name')->label('Task Name'),
            ExportColumn::make('total_seconds_spent')->label('Spent (Hours)')
                ->formatStateUsing(function (int $state): string {
                    return number_format($state / 3600, 1);
                }),

        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your task export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
