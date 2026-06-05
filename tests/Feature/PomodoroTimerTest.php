<?php

use App\Livewire\PomodoroTimer;
use App\Models\Column;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create());
});

function pomodoroTask(): Task
{
    $column = Column::create(['name' => 'Today', 'position' => 1, 'type' => 'fixed']);

    return Task::create([
        'name' => 'Focus work',
        'color' => 'blue',
        'board_column_id' => $column->id,
        'position' => 0,
        'date' => today(),
        'total_seconds_spent' => 0,
    ]);
}

afterEach(fn () => Carbon::setTestNow());

it('starts a running time entry and opens the panel', function () {
    $task = pomodoroTask();

    Livewire::test(PomodoroTimer::class)
        ->call('start', $task->id)
        ->assertSet('showPanel', true)
        ->assertSet('runningTaskId', $task->id);

    $entry = TimeEntry::sole();

    expect($entry->task_id)->toBe($task->id)
        ->and($entry->type)->toBe('pomodoro')
        ->and($entry->ended_at)->toBeNull()
        ->and($entry->seconds)->toBe(0);
});

it('stops the entry, computes seconds and updates total_seconds_spent', function () {
    $task = pomodoroTask();

    Carbon::setTestNow('2026-06-02 10:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    // 25 minutes later.
    Carbon::setTestNow('2026-06-02 10:25:00');
    $component->call('stop')
        ->assertSet('runningEntryId', null)
        ->assertDispatched('pomodoro-updated');

    $entry = TimeEntry::sole();

    expect($entry->ended_at)->not->toBeNull()
        ->and($entry->seconds)->toBe(1500)
        ->and($task->fresh()->total_seconds_spent)->toBe(1500);
});

it('stops the previous timer when a new one starts', function () {
    $task = pomodoroTask();
    $other = Task::create([
        'name' => 'Second task',
        'color' => 'red',
        'board_column_id' => $task->board_column_id,
        'position' => 1,
        'date' => today(),
        'total_seconds_spent' => 0,
    ]);

    Carbon::setTestNow('2026-06-02 09:00:00');
    $component = Livewire::test(PomodoroTimer::class)->call('start', $task->id);

    Carbon::setTestNow('2026-06-02 09:10:00');
    $component->call('start', $other->id);

    expect(TimeEntry::whereNull('ended_at')->count())->toBe(1)
        ->and(TimeEntry::whereNull('ended_at')->value('task_id'))->toBe($other->id)
        ->and($task->fresh()->total_seconds_spent)->toBe(600);
});

it('resumes a running entry on mount so a reload keeps the timer', function () {
    $task = pomodoroTask();
    $entry = TimeEntry::create([
        'task_id' => $task->id,
        'type' => 'pomodoro',
        'started_at' => now()->subMinutes(3),
        'ended_at' => null,
        'seconds' => 0,
    ]);

    Livewire::test(PomodoroTimer::class)
        ->assertSet('runningEntryId', $entry->id)
        ->assertSet('runningTaskId', $task->id)
        ->assertSet('runningTaskName', 'Focus work');
});

it('opens the panel and shows Change task when a different task modal opens', function () {
    $task = pomodoroTask();
    $other = Task::create([
        'name' => 'Other task',
        'color' => 'red',
        'board_column_id' => $task->board_column_id,
        'position' => 1,
        'date' => today(),
        'total_seconds_spent' => 0,
    ]);

    Livewire::test(PomodoroTimer::class)
        ->call('start', $task->id)
        ->call('togglePanel') // collapse first to prove setOpenTask re-opens it
        ->assertSet('showPanel', false)
        ->call('setOpenTask', $other->id, $other->name)
        ->assertSet('showPanel', true)
        ->assertSee('Change task')
        ->assertDontSee('Select open task');
});

it('keeps the panel open even when the open task matches the running task', function () {
    $task = pomodoroTask();

    Livewire::test(PomodoroTimer::class)
        ->call('start', $task->id)
        ->call('togglePanel')
        ->assertSet('showPanel', false)
        ->call('setOpenTask', $task->id, $task->name)
        ->assertSet('showPanel', true)
        ->assertDontSee('Change task');
});

it('reports today total seconds and completed pomodoro count', function () {
    $task = pomodoroTask();

    // Two completed pomodoros today.
    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => now(), 'seconds' => 1500]);
    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => now(), 'seconds' => 900]);
    // A running one should not count toward seconds or pomodoro count.
    TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => null, 'seconds' => 0]);

    $state = Livewire::test(PomodoroTimer::class);

    expect($state->instance()->todaySeconds)->toBe(2400)
        ->and($state->instance()->todayPomodoros)->toBe(2);
});
