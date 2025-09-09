<?php

namespace App\Filament\Resources\TaskResource\Actions;
use App\Models\Task;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadCsvAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->label('Download CSV')
            ->form([
                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->default(today()->startOfMonth())
                    ->required(),
                DatePicker::make('end_date')
                    ->label('End Date')
                    ->default(today()->endOfMonth())
                    ->required(),
            ])
            ->action(function (array $data): StreamedResponse {
                $startDate = Carbon::parse($data['start_date']);
                $endDate = Carbon::parse($data['end_date'])->endOfDay();

                return response()->streamDownload(function () use ($startDate, $endDate) {
                    $handle = fopen('php://output', 'w');
                    fputcsv($handle, ['Date', 'Task Name', 'Spent (Hours)']);

                    $tasks = Task::query()
                        ->withDefaultProjects()
                        ->whereBetween('date', [$startDate, $endDate])
                        ->orderBy('date', 'desc')
                        ->get();

                    $totalSpentHours = 0;

                    foreach ($tasks as $task) {
                        $spentHours = number_format($task->total_seconds_spent / 3600, 1);
                        $totalSpentHours += (float) $spentHours;

                        fputcsv($handle, [
                            $task->date,
                            $task->name,
                            $spentHours,

                        ]);
                    }

                    fputcsv($handle, ['Total', '', number_format($totalSpentHours, 1)]);

                    fclose($handle);
                }, 'tasks_export.csv');
            });
        ;
    }
}
