<?php

use App\Livewire\PomodoroPill;
use App\Models\Column;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create());
});

function pillTask(): Task
{
    $column = Column::create(['name' => 'Today', 'position' => 1, 'type' => 'fixed']);

    return Task::create([
        'name' => 'Focus',
        'color' => 'blue',
        'board_column_id' => $column->id,
        'position' => 0,
        'date' => today(),
        'total_seconds_spent' => 0,
    ]);
}

it('mirrors the running entry on mount', function () {
    $task = pillTask();
    $entry = TimeEntry::create([
        'task_id' => $task->id,
        'type' => 'pomodoro',
        'started_at' => now()->subMinutes(2),
        'ended_at' => null,
        'seconds' => 0,
    ]);

    Livewire::test(PomodoroPill::class)
        ->assertSet('runningEntryId', $entry->id);
});

it('shows nothing when no timer is running', function () {
    pillTask();

    Livewire::test(PomodoroPill::class)
        ->assertSet('runningEntryId', null)
        ->assertDontSee('countdown');
});

it('dispatches toggle-pomodoro when clicked', function () {
    pillTask();

    Livewire::test(PomodoroPill::class)
        ->call('toggle')
        ->assertDispatched('toggle-pomodoro');
});

it('refreshes its running state on the pomodoro-updated event', function () {
    $task = pillTask();

    $component = Livewire::test(PomodoroPill::class)
        ->assertSet('runningEntryId', null);

    $entry = TimeEntry::create([
        'task_id' => $task->id,
        'type' => 'pomodoro',
        'started_at' => now(),
        'ended_at' => null,
        'seconds' => 0,
    ]);

    $component->call('loadRunningEntry')
        ->assertSet('runningEntryId', $entry->id);
});
