<?php

namespace App\Filament\Pages;

use App\Models\Column;
use App\Models\Project;
use App\Models\SubTask;
use App\Models\Task;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class TaskBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Task Board';

    protected static ?string $title = 'Task Board';

    protected static ?int $navigationSort = -2;

    protected static string $view = 'filament.pages.task-board';

    /** Modal state */
    public bool $showTaskModal = false;

    public ?int $editingTaskId = null;

    public array $taskForm = [
        'name' => '',
        'color' => '',
        'description' => '',
        'board_column_id' => null,
        'new_subtask' => '',
    ];

    protected function rules(): array
    {
        return [
            'taskForm.name' => ['required', 'string', 'max:255'],
            'taskForm.color' => ['required', 'string'],
            'taskForm.board_column_id' => ['required', 'integer', 'exists:columns,id'],
            'taskForm.description' => ['nullable', 'string'],
        ];
    }

    /** Re-render so time-spent badges reflect a just-stopped timer. */
    #[On('pomodoro-updated')]
    public function refreshBoard(): void
    {
        // The empty body is enough — Livewire re-renders on every action.
    }

    /**
     * Columns with their ordered tasks for rendering.
     */
    public function getBoardColumns()
    {
        return Column::orderBy('position')
            ->with([
                'tasks' => fn ($q) => $q->orderBy('position'),
                'tasks.subTasks',
                'tasks.project',
            ])
            ->get();
    }

    public function getProjects()
    {
        return Project::orderBy('name')->get();
    }

    public function getEditingSubtasks()
    {
        if (! $this->editingTaskId) {
            return collect();
        }

        return SubTask::where('task_id', $this->editingTaskId)->orderBy('id')->get();
    }

    /**
     * Persist a drag-and-drop move: reassign the column and renumber positions
     * for every task in the destination column.
     */
    public function moveTask(int $taskId, int $columnId, array $orderedIds): void
    {
        DB::transaction(function () use ($taskId, $columnId, $orderedIds) {
            Task::whereKey($taskId)->update(['board_column_id' => $columnId]);

            foreach ($orderedIds as $index => $id) {
                Task::whereKey($id)->update([
                    'board_column_id' => $columnId,
                    'position' => $index,
                ]);
            }
        });
    }

    public function newTask(int $columnId): void
    {
        $this->resetValidation();
        $this->editingTaskId = null;
        $this->taskForm = [
            'name' => '',
            'color' => $this->getProjects()->first()?->color ?? '',
            'description' => '',
            'board_column_id' => $columnId,
            'new_subtask' => '',
        ];
        $this->showTaskModal = true;
    }

    public function editTask(int $taskId): void
    {
        $task = Task::findOrFail($taskId);
        $this->resetValidation();
        $this->editingTaskId = $task->id;
        $this->taskForm = [
            'name' => $task->name,
            'color' => $task->color,
            'description' => $task->description ?? '',
            'board_column_id' => $task->board_column_id,
            'new_subtask' => '',
        ];
        $this->showTaskModal = true;
    }

    public function saveTask(): void
    {
        $this->validate();

        if ($this->editingTaskId) {
            $task = Task::findOrFail($this->editingTaskId);
            $task->update([
                'name' => $this->taskForm['name'],
                'color' => $this->taskForm['color'],
                'description' => $this->taskForm['description'] ?: null,
                'board_column_id' => $this->taskForm['board_column_id'],
            ]);
        } else {
            $position = (int) Task::where('board_column_id', $this->taskForm['board_column_id'])->max('position');

            Task::create([
                'name' => $this->taskForm['name'],
                'color' => $this->taskForm['color'],
                'description' => $this->taskForm['description'] ?: null,
                'board_column_id' => $this->taskForm['board_column_id'],
                'position' => $position + 1,
                'date' => today(),
                'total_seconds_spent' => 0,
                'total_seconds_estimate' => 0,
            ]);
        }

        $this->closeTaskModal();
    }

    public function deleteTask(int $taskId): void
    {
        Task::whereKey($taskId)->delete();

        if ($this->editingTaskId === $taskId) {
            $this->closeTaskModal();
        }
    }

    public function addSubtask(): void
    {
        $name = trim($this->taskForm['new_subtask'] ?? '');

        if (! $this->editingTaskId || $name === '') {
            return;
        }

        SubTask::create([
            'task_id' => $this->editingTaskId,
            'name' => $name,
            'finished' => false,
        ]);

        $this->taskForm['new_subtask'] = '';
    }

    public function toggleSubtask(int $subTaskId): void
    {
        $subTask = SubTask::findOrFail($subTaskId);
        $subTask->update(['finished' => ! $subTask->finished]);
    }

    public function deleteSubtask(int $subTaskId): void
    {
        SubTask::whereKey($subTaskId)->delete();
    }

    public function closeTaskModal(): void
    {
        $this->showTaskModal = false;
        $this->editingTaskId = null;
        $this->reset('taskForm');
    }
}
