<?php

use App\Filament\Pages\TaskBoard;
use App\Models\Column;
use App\Models\SubTask;
use App\Models\Task;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create());
});

function makeColumn(string $name, int $position): Column
{
    return Column::create([
        'name' => $name,
        'position' => $position,
        'type' => 'fixed',
    ]);
}

it('creates a new task via saveTask', function () {
    $column = makeColumn('Today', 1);

    Livewire::test(TaskBoard::class)
        ->call('newTask', $column->id)
        ->assertSet('showTaskModal', true)
        ->set('taskForm.name', 'Write tests')
        ->set('taskForm.color', 'blue')
        ->call('saveTask')
        ->assertHasNoErrors()
        ->assertSet('showTaskModal', false);

    $task = Task::where('name', 'Write tests')->sole();

    expect($task->board_column_id)->toBe($column->id)
        ->and($task->color)->toBe('blue')
        ->and($task->position)->toBe(1)
        ->and((string) $task->date->toDateString())->toBe(today()->toDateString());
});

it('appends new tasks to the end of a column', function () {
    $column = makeColumn('Today', 1);
    Task::create(['name' => 'First', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 5, 'date' => today()]);

    Livewire::test(TaskBoard::class)
        ->call('newTask', $column->id)
        ->set('taskForm.name', 'Second')
        ->set('taskForm.color', 'red')
        ->call('saveTask')
        ->assertHasNoErrors();

    expect(Task::where('name', 'Second')->value('position'))->toBe(6);
});

it('validates that name, color and column are required on saveTask', function () {
    Livewire::test(TaskBoard::class)
        ->set('taskForm.name', '')
        ->set('taskForm.color', '')
        ->set('taskForm.board_column_id', null)
        ->call('saveTask')
        ->assertHasErrors([
            'taskForm.name' => 'required',
            'taskForm.color' => 'required',
            'taskForm.board_column_id' => 'required',
        ]);

    expect(Task::count())->toBe(0);
});

it('edits an existing task via saveTask', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Old name', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 1, 'date' => today()]);

    Livewire::test(TaskBoard::class)
        ->call('editTask', $task->id)
        ->assertSet('editingTaskId', $task->id)
        ->set('taskForm.name', 'New name')
        ->set('taskForm.color', 'green')
        ->call('saveTask')
        ->assertHasNoErrors();

    $task->refresh();

    expect($task->name)->toBe('New name')
        ->and($task->color)->toBe('green');
});

it('moves a task to another column and renumbers positions', function () {
    $source = makeColumn('Today', 1);
    $dest = makeColumn('Monday', 5);

    $a = Task::create(['name' => 'A', 'color' => 'blue', 'board_column_id' => $dest->id, 'position' => 0, 'date' => today()]);
    $b = Task::create(['name' => 'B', 'color' => 'blue', 'board_column_id' => $dest->id, 'position' => 1, 'date' => today()]);
    $moved = Task::create(['name' => 'Moved', 'color' => 'blue', 'board_column_id' => $source->id, 'position' => 0, 'date' => today()]);

    // Drop "Moved" between A and B in the destination column.
    Livewire::test(TaskBoard::class)
        ->call('moveTask', $moved->id, $dest->id, [$a->id, $moved->id, $b->id]);

    expect($moved->fresh()->board_column_id)->toBe($dest->id);

    expect($a->fresh()->position)->toBe(0)
        ->and($moved->fresh()->position)->toBe(1)
        ->and($b->fresh()->position)->toBe(2);
});

it('opens and closes the read-only detail modal', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Detail me', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 0, 'date' => today()]);

    $component = Livewire::test(TaskBoard::class)
        ->call('viewTask', $task->id)
        ->assertSet('viewingTaskId', $task->id);

    expect($component->instance()->getViewingTask()->id)->toBe($task->id);

    $component->call('closeDetailModal')->assertSet('viewingTaskId', null);
});

it('adds a subtask from the detail modal', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Has subs', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 0, 'date' => today()]);

    Livewire::test(TaskBoard::class)
        ->call('viewTask', $task->id)
        ->set('newSubtask', 'New step')
        ->call('addDetailSubtask')
        ->assertSet('newSubtask', '');

    expect(SubTask::where('task_id', $task->id)->where('name', 'New step')->exists())->toBeTrue();
});

it('moves the viewing task to another column via the rail, appended to the end', function () {
    $source = makeColumn('Today', 1);
    $dest = makeColumn('Monday', 5);
    Task::create(['name' => 'Existing', 'color' => 'blue', 'board_column_id' => $dest->id, 'position' => 3, 'date' => today()]);
    $task = Task::create(['name' => 'Mover', 'color' => 'blue', 'board_column_id' => $source->id, 'position' => 0, 'date' => today()]);

    Livewire::test(TaskBoard::class)
        ->call('viewTask', $task->id)
        ->call('moveViewingTask', $dest->id);

    $task->refresh();
    expect($task->board_column_id)->toBe($dest->id)
        ->and($task->position)->toBe(4);
});

it('switches from detail to the edit form', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Editable', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 0, 'date' => today()]);

    Livewire::test(TaskBoard::class)
        ->call('viewTask', $task->id)
        ->call('editFromDetail')
        ->assertSet('viewingTaskId', null)
        ->assertSet('editingTaskId', $task->id)
        ->assertSet('showTaskModal', true);
});

it('exposes the running time entry for running-card styling', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Running', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 0, 'date' => today()]);
    \App\Models\TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => null, 'seconds' => 0]);

    $entry = Livewire::test(TaskBoard::class)->instance()->getRunningEntry();

    expect($entry)->not->toBeNull()
        ->and($entry->task_id)->toBe($task->id);
});

it('opens the task-history modal from the detail rail and renders the entries', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Tracked', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 0, 'date' => today()]);
    $other = Task::create(['name' => 'Other', 'color' => 'red', 'board_column_id' => $column->id, 'position' => 1, 'date' => today()]);

    \App\Models\TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addMinutes(25), 'seconds' => 1500]);
    \App\Models\TimeEntry::create(['task_id' => $task->id, 'type' => 'stopwatch', 'started_at' => now()->subHour(), 'ended_at' => now()->subMinutes(30), 'seconds' => 1800]);
    // Another task's entry and a still-running entry must both stay out.
    \App\Models\TimeEntry::create(['task_id' => $other->id, 'type' => 'pomodoro', 'started_at' => now()->subHours(2), 'ended_at' => now()->subHours(2)->addMinutes(25), 'seconds' => 1500]);
    \App\Models\TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => null, 'seconds' => 0]);

    $component = Livewire::test(TaskBoard::class)
        ->call('viewTask', $task->id)
        ->call('openTaskHistory')
        ->assertSet('showTaskHistory', true)
        ->assertSee('Successful Pomodoro');

    $days = $component->instance()->getTaskHistoryDays();

    expect($days)->toHaveCount(2)
        ->and($days[0]['label'])->toBe('Today')
        ->and($days[0]['entries'])->toHaveCount(1)
        ->and($days[1]['label'])->toBe('Yesterday')
        ->and($days[1]['pomodoros'])->toBe(1);
});

it('filters the task history by period', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Tracked', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 0, 'date' => today()]);

    \App\Models\TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now()->subMonths(2), 'ended_at' => now()->subMonths(2)->addMinutes(25), 'seconds' => 1500]);
    \App\Models\TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now(), 'ended_at' => now()->addMinutes(25), 'seconds' => 1500]);

    $component = Livewire::test(TaskBoard::class)
        ->call('viewTask', $task->id)
        ->call('openTaskHistory');

    expect($component->instance()->getTaskHistoryDays()->sum(fn ($day) => $day['entries']->count()))->toBe(2);

    $component->set('historyPeriod', 'today');

    expect($component->instance()->getTaskHistoryDays()->sum(fn ($day) => $day['entries']->count()))->toBe(1);
});

it('deletes a history entry and rolls its time off the task', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Tracked', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 0, 'date' => today(), 'total_seconds_spent' => 1500]);
    $entry = \App\Models\TimeEntry::create(['task_id' => $task->id, 'type' => 'pomodoro', 'started_at' => now()->subHour(), 'ended_at' => now()->subHour()->addMinutes(25), 'seconds' => 1500]);

    Livewire::test(TaskBoard::class)
        ->call('viewTask', $task->id)
        ->call('openTaskHistory')
        ->call('deleteHistoryEntry', $entry->id)
        ->assertDispatched('pomodoro-updated');

    expect(\App\Models\TimeEntry::find($entry->id))->toBeNull()
        ->and($task->fresh()->total_seconds_spent)->toBe(0);
});

it('closes the history modal when the detail modal closes', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Tracked', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 0, 'date' => today()]);

    Livewire::test(TaskBoard::class)
        ->call('viewTask', $task->id)
        ->call('openTaskHistory')
        ->assertSet('showTaskHistory', true)
        ->call('closeTaskHistory')
        ->assertSet('showTaskHistory', false)
        ->call('openTaskHistory')
        ->call('closeDetailModal')
        ->assertSet('showTaskHistory', false);
});

it('toggles a subtask finished state', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Task', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 0, 'date' => today()]);
    $subTask = SubTask::create(['task_id' => $task->id, 'name' => 'Step', 'finished' => false]);

    Livewire::test(TaskBoard::class)->call('toggleSubtask', $subTask->id);
    expect((bool) $subTask->fresh()->finished)->toBeTrue();

    Livewire::test(TaskBoard::class)->call('toggleSubtask', $subTask->id);
    expect((bool) $subTask->fresh()->finished)->toBeFalse();
});
