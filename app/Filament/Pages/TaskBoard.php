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

    /** Detail-view modal state (sits on top of the edit form). */
    public ?int $viewingTaskId = null;

    /** Inline "add subtask" field used by the detail modal. */
    public string $newSubtask = '';

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

    /**
     * The currently running time entry (if any) so cards can render a running
     * highlight and the live "+Xm" delta. Re-queried on every render, and the
     * board re-renders on the `pomodoro-updated` event.
     */
    public function getRunningEntry(): ?\App\Models\TimeEntry
    {
        return \App\Models\TimeEntry::running()->latest('started_at')->first();
    }

    public function getEditingSubtasks()
    {
        if (! $this->editingTaskId) {
            return collect();
        }

        return SubTask::where('task_id', $this->editingTaskId)->orderBy('id')->get();
    }

    /** The task shown in the read-only detail modal (with its relations). */
    public function getViewingTask(): ?Task
    {
        if (! $this->viewingTaskId) {
            return null;
        }

        return Task::with(['subTasks' => fn ($q) => $q->orderBy('id'), 'project', 'column'])
            ->find($this->viewingTaskId);
    }

    /** Open the read-only detail modal for a card. */
    public function viewTask(int $taskId): void
    {
        $this->viewingTaskId = $taskId;
        $this->newSubtask = '';

        // Let the Pomodoro panel offer "Select open task" for this card.
        $this->dispatch('task-opened', taskId: $taskId, name: Task::find($taskId)?->name);
    }

    public function closeDetailModal(): void
    {
        $this->viewingTaskId = null;
        $this->newSubtask = '';

        $this->dispatch('task-closed');
    }

    /** Add a subtask to the task currently shown in the detail modal. */
    public function addDetailSubtask(): void
    {
        $name = trim($this->newSubtask);

        if (! $this->viewingTaskId || $name === '') {
            return;
        }

        SubTask::create([
            'task_id' => $this->viewingTaskId,
            'name' => $name,
            'finished' => false,
        ]);

        $this->newSubtask = '';
    }

    /** Move the detail task to another column (appended to its end). */
    public function moveViewingTask(int $columnId): void
    {
        if (! $this->viewingTaskId) {
            return;
        }

        $position = (int) Task::where('board_column_id', $columnId)->max('position');

        Task::whereKey($this->viewingTaskId)->update([
            'board_column_id' => $columnId,
            'position' => $position + 1,
        ]);
    }

    /** Switch from the detail modal into the editable form. */
    public function editFromDetail(): void
    {
        $taskId = $this->viewingTaskId;
        $this->closeDetailModal();

        if ($taskId) {
            $this->editTask($taskId);
        }
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

    /** Context-menu "Move > Move right": shift the task into the next column. */
    public function moveTaskRight(int $taskId): void
    {
        $task = Task::find($taskId);

        if (! $task || ! $task->board_column_id) {
            return;
        }

        $currentPosition = Column::whereKey($task->board_column_id)->value('position');

        if ($currentPosition === null) {
            return;
        }

        $nextColumn = Column::where('position', '>', $currentPosition)
            ->orderBy('position')
            ->first();

        if (! $nextColumn) {
            return;
        }

        $position = (int) Task::where('board_column_id', $nextColumn->id)->max('position');

        $task->update([
            'board_column_id' => $nextColumn->id,
            'position' => $position + 1,
        ]);
    }

    /** Context-menu "Move > Move to top": float the task above its column peers. */
    public function moveTaskToTop(int $taskId): void
    {
        $this->reorderWithinColumn($taskId, 'top');
    }

    /** Context-menu "Move > Move to bottom": sink the task below its column peers. */
    public function moveTaskToBottom(int $taskId): void
    {
        $this->reorderWithinColumn($taskId, 'bottom');
    }

    /** Renumber a task's column with that task pinned to the top or bottom. */
    private function reorderWithinColumn(int $taskId, string $edge): void
    {
        $task = Task::find($taskId);

        if (! $task || ! $task->board_column_id) {
            return;
        }

        $ids = Task::where('board_column_id', $task->board_column_id)
            ->orderBy('position')
            ->pluck('id')
            ->reject(fn ($id) => $id === $task->id)
            ->values()
            ->all();

        if ($edge === 'top') {
            array_unshift($ids, $task->id);
        } else {
            $ids[] = $task->id;
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                Task::whereKey($id)->update(['position' => $index]);
            }
        });
    }

    /** Context-menu "Copy here": duplicate the task (and its subtasks) just below the original. */
    public function copyTask(int $taskId): void
    {
        $task = Task::with('subTasks')->find($taskId);

        if (! $task) {
            return;
        }

        DB::transaction(function () use ($task) {
            $copy = $task->replicate();
            $copy->kanbanflow_task_id = null;
            $copy->total_seconds_spent = 0;
            $copy->save();

            foreach ($task->subTasks as $subTask) {
                SubTask::create([
                    'task_id' => $copy->id,
                    'name' => $subTask->name,
                    'finished' => $subTask->finished,
                ]);
            }

            // Renumber the column so the copy sits immediately after the original.
            $ids = Task::where('board_column_id', $task->board_column_id)
                ->where('id', '!=', $copy->id)
                ->orderBy('position')
                ->pluck('id')
                ->all();

            $insertAt = array_search($task->id, $ids, true);
            array_splice($ids, $insertAt === false ? count($ids) : $insertAt + 1, 0, [$copy->id]);

            foreach ($ids as $index => $id) {
                Task::whereKey($id)->update(['position' => $index]);
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

        if ($this->viewingTaskId === $taskId) {
            $this->closeDetailModal();
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
