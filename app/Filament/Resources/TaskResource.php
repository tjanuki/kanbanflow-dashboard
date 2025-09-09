<?php

namespace App\Filament\Resources;

use App\Filament\Exports\TaskExporter;
use App\Filament\Resources\TaskResource\Actions\DownloadCsvAction;
use App\Filament\Resources\TaskResource\Pages;
use App\Models\Project;
use App\Models\Task;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(Task::getForm());
    }

    public static function table(Table $table): Table
    {
        $mainProject = Project::where('is_default', true)->orderBy('id')->first();
        return $table
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(100)
            ->columns([
                Tables\Columns\TextColumn::make('date')->sortable(),
                Tables\Columns\TextColumn::make('project.name')->label('Project'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('total_seconds_spent')
                    ->label('Spent (Hours)')
                    ->formatStateUsing(fn (int $state) => number_format($state / 3600, 2))
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->formatStateUsing(fn (int $state
                        ) => number_format($state / 3600, 2)),
                    ]),
            ])
            ->defaultSort('date', 'desc')
            ->persistSortInSession()
            ->filters([
                // filter by project
                Tables\Filters\SelectFilter::make('color')
                    ->options(fn () => \App\Models\Project::pluck('name', 'color')->toArray())
                    ->label('Project')
                    ->default(null),
                // filtered by date
                Filter::make('month_year')
                    ->form([
                        Forms\Components\Select::make('month')
                            ->options([
                                '1' => 'January', '2' => 'February', '3' => 'March',
                                '4' => 'April', '5' => 'May', '6' => 'June',
                                '7' => 'July', '8' => 'August', '9' => 'September',
                                '10' => 'October', '11' => 'November', '12' => 'December',
                            ])
                            ->label('Month')
                            ->default(now()->month)
                            ->required(),
                        Forms\Components\TextInput::make('year')
                            ->numeric()
                            ->label('Year')
                            ->default(now()->year)
                            ->required(),
                    ])
                    ->query(function ($query, array $data) {
                        $month = intval($data['month']) ?? now()->month;
                        $year = intval($data['year']) ?? now()->year;
                        $startOfMonth = now()->setYear($year)->setMonth($month)->startOfMonth();
                        $endOfMonth = now()->setYear($year)->setMonth($month)->endOfMonth();

                        $query->whereBetween('date', [$startOfMonth, $endOfMonth]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalHeading('Edit Task'),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make('Export')
                    ->exporter(TaskExporter::class)
                    ->formats([ExportFormat::Csv]),
                DownloadCsvAction::make('Download CSV'),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
        ];
    }
}
