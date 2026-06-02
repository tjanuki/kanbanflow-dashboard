<?php

namespace App\Models;

use Filament\Forms;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'kanbanflow_task_id',
        'date',
        'name',
        'description',
        'color',
        'column_id',
        'board_column_id',
        'position',
        'total_seconds_spent',
        'total_seconds_estimate',
        'changed_properties',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public static function getForm(): array
    {
        return [
            Forms\Components\TextInput::make('kanbanflow_task_id')
                ->label('Kanbanflow Task ID')
                ->required(),
            Forms\Components\DatePicker::make('date')
                ->label('Date')
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->required(),
            Forms\Components\Textarea::make('description')
                ->label('Description'),
            // set projects as color
            Forms\Components\Select::make('color')
                ->label('Project')
                ->relationship('project', 'name')
                ->required(),
            Forms\Components\TextInput::make('column_id')
                ->label('Column ID')
                ->required(),
            Forms\Components\TextInput::make('total_seconds_spent')
                ->label('Total Seconds Spent')
                ->required(),
            Forms\Components\TextInput::make('total_seconds_estimate')
                ->label('Total Seconds Estimate')
                ->required(),

        ];
    }

    public function subTasks(): HasMany
    {
        return $this->hasMany(SubTask::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * Sum the related time entries back into total_seconds_spent so the
     * existing dashboard widgets keep working off a single number.
     */
    public function recalculateSecondsSpent(): void
    {
        $this->update([
            'total_seconds_spent' => (int) $this->timeEntries()->sum('seconds'),
        ]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'color', 'color');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class, 'board_column_id');
    }

    public function scopeWithDefaultProjects($query)
    {
        return $query->whereIn('color', function ($query) {
            $query->select('color')
                ->from('projects')
                ->where('is_default', true);
        });
    }
}
