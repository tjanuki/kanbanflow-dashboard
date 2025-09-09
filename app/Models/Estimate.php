<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;

class Estimate extends Model
{
    use HasFactory;

    public function setEstimatedHoursAttribute(float $value): void
    {
        $this->attributes['estimated_seconds'] = $value * 3600;
    }

    public function getEstimatedHoursAttribute(): float|int
    {
        return $this->estimated_seconds / 3600;
    }

    public static function getForm()
    {

        return [
            Forms\Components\DatePicker::make('date')
                ->label('Date')
                ->required(),
            Forms\Components\TextInput::make('estimated_hours')
                ->label('Estimated Hours')
                ->helperText('Please enter the estimated time in hours.')
                ->required()
                ->numeric()
                ->step('0.5')
                ->afterStateHydrated(function ($component, $record) {
                    if ($record) {
                        $component->state(number_format($record->estimated_hours, 1));
                    }
                })
        ];
    }

    public function scopeWithDefaultProjects($query)
    {
        return $query->leftJoin('tasks', function ($join) {
            $join->on('estimates.date', '=', 'tasks.date')
                ->join('projects', function ($join) {
                    $join->on('tasks.color', '=', 'projects.color')
                        ->where('projects.is_default', true);
                });
        });
    }
}
