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

it('toggles a subtask finished state', function () {
    $column = makeColumn('Today', 1);
    $task = Task::create(['name' => 'Task', 'color' => 'blue', 'board_column_id' => $column->id, 'position' => 0, 'date' => today()]);
    $subTask = SubTask::create(['task_id' => $task->id, 'name' => 'Step', 'finished' => false]);

    Livewire::test(TaskBoard::class)->call('toggleSubtask', $subTask->id);
    expect((bool) $subTask->fresh()->finished)->toBeTrue();

    Livewire::test(TaskBoard::class)->call('toggleSubtask', $subTask->id);
    expect((bool) $subTask->fresh()->finished)->toBeFalse();
});
