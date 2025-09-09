<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EstimateResource\Pages;
use App\Filament\Resources\EstimateResource\RelationManagers;
use App\Models\Estimate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EstimateResource extends Resource
{
    protected static ?string $model = Estimate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(Estimate::getForm());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')->sortable(),
                Tables\Columns\TextColumn::make('estimated_seconds')
                    ->formatStateUsing(fn(int $state) => number_format($state / 3600, 1))
                    ->sortable(),
            ])
            ->defaultSort('date', )
            ->defaultPaginationPageOption(50)
            ->filters([
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
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make()->form(Estimate::getForm()),
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
            'index' => Pages\ListEstimates::route('/'),
        ];
    }
}
