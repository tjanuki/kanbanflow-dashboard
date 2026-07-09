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

    /** When true, the task-history modal (detail rail's Reports button) is showing. */
    public bool $showTaskHistory = false;

    /** Task-history period filter (same options as the Timer log). */
    public string $historyPeriod = 'all';

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
     * Re-render when the colour list changes in the timer's "Edit color"
     * dialog, so the detail-modal selector and edit-form pills stay current.
     */
    #[On('projects-updated')]
    public function refreshProjects(): void
    {
        // Empty body — the re-render alone refreshes getProjects().
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
        $this->showTaskHistory = false;

        $this->dispatch('task-closed');
    }

    /** Open the history modal for the task shown in the detail modal. */
    public function openTaskHistory(): void
    {
        if ($this->viewingTaskId) {
            $this->showTaskHistory = true;
        }
    }

    public function closeTaskHistory(): void
    {
        $this->showTaskHistory = false;
    }

    /**
     * "Add item" in the history modal: open the timer's Add-time dialog for this
     * task. A date (from a specific day group's header) pre-fills that day.
     */
    public function openHistoryAddTime(?string $date = null): void
    {
        if ($this->viewingTaskId) {
            $this->dispatch('open-add-time', taskId: $this->viewingTaskId, date: $date);
        }
    }

    /**
     * "Edit" on a history entry: open the timer's time dialog pre-filled with
     * that entry so its task/date/times can be changed in place.
     */
    public function openHistoryEditTime(int $entryId): void
    {
        if ($this->viewingTaskId) {
            $this->dispatch('open-edit-time', entryId: $entryId);
        }
    }

    /**
     * Completed entries for the viewing task's history modal, filtered by
     * period and grouped by day — the same shape the Timer log renders.
     */
    public function getTaskHistoryDays(): \Illuminate\Support\Collection
    {
        if (! $this->viewingTaskId) {
            return collect();
        }

        $start = match ($this->historyPeriod) {
            'today' => today(),
            'this_week' => now()->startOfWeek(),
            'this_last_week' => now()->subWeek()->startOfWeek(),
            'this_month' => now()->startOfMonth(),
            default => null, // all time
        };

        return \App\Models\TimeEntry::where('task_id', $this->viewingTaskId)
            ->whereNotNull('ended_at')
            ->when($start, fn ($query) => $query->where('started_at', '>=', $start))
            ->orderBy('started_at')
            ->get()
            ->groupBy(fn (\App\Models\TimeEntry $entry) => $entry->started_at->toDateString())
            ->map(function ($entries, string $date) {
                $day = \Illuminate\Support\Carbon::parse($date);

                return [
                    'date' => $day,
                    'label' => $day->isToday() ? 'Today' : ($day->isYesterday() ? 'Yesterday' : $day->format('l, j F')),
                    'seconds' => (int) $entries->sum('seconds'),
                    'pomodoros' => $entries->where('type', 'pomodoro')->count(),
                    'entries' => $entries,
                ];
            })
            ->sortByDesc(fn (array $day) => $day['date'])
            ->values();
    }

    /** Remove an entry from the history modal and roll its time off the task. */
    public function deleteHistoryEntry(int $entryId): void
    {
        $entry = \App\Models\TimeEntry::find($entryId);

        if (! $entry) {
            return;
        }

        $task = $entry->task;
        $entry->delete();
        $task?->recalculateSecondsSpent();

        // Refresh the timer panel / board badges that show this task's totals.
        $this->dispatch('pomodoro-updated');
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

    /** Move the detail task to another column. */
    public function moveViewingTask(int $columnId): void
    {
        if (! $this->viewingTaskId) {
            return;
        }

        $task = Task::find($this->viewingTaskId);

        if (! $task) {
            return;
        }

        $task->board_column_id = $columnId;

        if ($this->isDoneColumn($columnId)) {
            // Sink it to the bottom of today's Done group, not the whole column.
            $this->appendToDoneToday($task);
        } else {
            $task->position = (int) Task::where('board_column_id', $columnId)->max('position') + 1;
            $task->save();
        }
    }

    /** Apply a colour to the task shown in the detail modal (stays open). */
    public function setViewingTaskColor(string $color): void
    {
        if (! $this->viewingTaskId || ! \App\Support\Palette::has($color)) {
            return;
        }

        Task::whereKey($this->viewingTaskId)->update(['color' => $color]);
    }

    /** Rename the task shown in the detail modal (saved on blur/enter). */
    public function renameViewingTask(string $name): void
    {
        $name = trim($name);

        if (! $this->viewingTaskId || $name === '') {
            return;
        }

        Task::whereKey($this->viewingTaskId)->update(['name' => $name]);
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
     * for every task in the destination column, preserving the exact drop order.
     *
     * Drag-and-drop is the manual, position-respecting path — it reorders cards
     * within the Done column and drags them out to other columns. Only a card
     * crossing *into* Done from elsewhere is date-stamped (so it files under the
     * "Today" group); the raw drop position is otherwise left untouched.
     */
    public function moveTask(int $taskId, int $columnId, array $orderedIds): void
    {
        $task = Task::find($taskId);

        if (! $task) {
            return;
        }

        $enteringDone = $this->isDoneColumn($columnId) && ! $this->isDoneColumn((int) $task->board_column_id);

        DB::transaction(function () use ($task, $columnId, $orderedIds, $enteringDone) {
            foreach ($orderedIds as $index => $id) {
                Task::whereKey($id)->update([
                    'board_column_id' => $columnId,
                    'position' => $index,
                ]);
            }

            if ($enteringDone) {
                $task->completed_at = now();
                $task->save();
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

        $task->board_column_id = $nextColumn->id;

        if ($this->isDoneColumn($nextColumn->id)) {
            $this->appendToDoneToday($task);

            return;
        }

        $task->position = (int) Task::where('board_column_id', $nextColumn->id)->max('position') + 1;
        $task->save();
    }

    /** Is this column the date-grouped "Done" column? */
    private function isDoneColumn(int $columnId): bool
    {
        return Column::whereKey($columnId)->value('name') === 'Done';
    }

    /**
     * Stamp a task as completed today and place it at the bottom of the Done
     * column's "Today" date group (not the very bottom of the column, since the
     * newest group renders at the top).
     */
    private function appendToDoneToday(Task $task): void
    {
        $task->completed_at = now();

        $todayGroup = Task::where('board_column_id', $task->board_column_id)
            ->where('id', '!=', $task->id)
            ->whereDate('completed_at', today());

        // Bottom of today's group, or after everything if today's group is empty.
        $base = $todayGroup->exists()
            ? (int) $todayGroup->max('position')
            : (int) Task::where('board_column_id', $task->board_column_id)
                ->where('id', '!=', $task->id)
                ->max('position');

        $task->position = $base + 1;
        $task->save();
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

        $copyId = null;

        DB::transaction(function () use ($task, &$copyId) {
            $copy = $task->replicate();
            $copy->kanbanflow_task_id = null;
            $copy->total_seconds_spent = 0;
            $copy->save();
            $copyId = $copy->id;

            foreach ($task->subTasks as $subTask) {
                SubTask::create([
                    'task_id' => $copy->id,
                    'name' => $subTask->name,
                    'finished' => false,
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

        // Let the board blink the freshly created copy once it re-renders.
        $this->dispatch('task-copied', id: $copyId);
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
