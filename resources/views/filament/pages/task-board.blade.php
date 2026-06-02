<x-filament-panels::page>
    @php
        // KanbanFlow colour names -> background / accent tints.
        $palette = [
            'white' => ['bg' => '#f3f4f6', 'dot' => '#9ca3af'],
            'yellow' => ['bg' => '#fef9c3', 'dot' => '#eab308'],
            'green' => ['bg' => '#dcfce7', 'dot' => '#22c55e'],
            'blue' => ['bg' => '#dbeafe', 'dot' => '#3b82f6'],
            'purple' => ['bg' => '#ede9fe', 'dot' => '#8b5cf6'],
            'red' => ['bg' => '#fee2e2', 'dot' => '#ef4444'],
            'orange' => ['bg' => '#ffedd5', 'dot' => '#f97316'],
            'magenta' => ['bg' => '#fce7f3', 'dot' => '#ec4899'],
            'cyan' => ['bg' => '#cffafe', 'dot' => '#06b6d4'],
            'brown' => ['bg' => '#efebe9', 'dot' => '#8d6e63'],
        ];
        $tint = fn ($color) => $palette[$color] ?? ['bg' => '#f3f4f6', 'dot' => '#9ca3af'];
        $fmt = function (int $s): string {
            $h = intdiv($s, 3600);
            $m = intdiv($s % 3600, 60);
            return $h ? "{$h}h {$m}m" : "{$m}m";
        };
    @endphp

    <div class="flex gap-3 overflow-x-auto pb-4">
        @foreach ($this->getBoardColumns() as $column)
            <div class="flex w-72 flex-shrink-0 flex-col rounded-xl bg-gray-100 dark:bg-gray-800/60">
                {{-- Column header --}}
                <div class="flex items-center justify-between px-3 py-2">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $column->name }}</span>
                        <span class="rounded-full bg-gray-200 px-2 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                            {{ $column->tasks->count() }}
                        </span>
                    </div>
                    <button
                        type="button"
                        wire:click="newTask({{ $column->id }})"
                        class="flex h-6 w-6 items-center justify-center rounded-md text-gray-500 hover:bg-gray-200 hover:text-primary-600 dark:hover:bg-gray-700"
                        title="Add task"
                    >
                        <x-heroicon-m-plus class="h-4 w-4" />
                    </button>
                </div>

                {{-- Droppable task list --}}
                <div
                    data-column-id="{{ $column->id }}"
                    class="flex min-h-[60px] flex-col gap-2 px-2 pb-3"
                >
                    @foreach ($column->tasks as $task)
                        @php $t = $tint($task->color); @endphp
                        <div
                            data-task-id="{{ $task->id }}"
                            wire:key="task-{{ $task->id }}"
                            class="group cursor-grab rounded-lg border-l-4 p-2 shadow-sm active:cursor-grabbing"
                            style="background-color: {{ $t['bg'] }}; border-left-color: {{ $t['dot'] }};"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <button
                                    type="button"
                                    wire:click="editTask({{ $task->id }})"
                                    class="flex-1 text-left text-sm font-medium text-gray-800"
                                >
                                    {{ $task->name }}
                                </button>
                                <button
                                    type="button"
                                    wire:click="deleteTask({{ $task->id }})"
                                    wire:confirm="Delete this task?"
                                    class="text-gray-400 opacity-0 transition group-hover:opacity-100 hover:text-red-600"
                                    title="Delete task"
                                >
                                    <x-heroicon-m-trash class="h-4 w-4" />
                                </button>
                            </div>

                            {{-- Subtasks --}}
                            @if ($task->subTasks->isNotEmpty())
                                <div class="mt-2 space-y-1">
                                    @foreach ($task->subTasks as $subTask)
                                        <label class="flex items-center gap-1.5 text-xs text-gray-600">
                                            <input
                                                type="checkbox"
                                                @checked($subTask->finished)
                                                wire:click="toggleSubtask({{ $subTask->id }})"
                                                class="h-3 w-3 rounded border-gray-300 text-primary-600"
                                            />
                                            <span @class(['line-through opacity-60' => $subTask->finished])>{{ $subTask->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Footer: project + time spent + start timer --}}
                            <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                                <span class="flex items-center gap-1">
                                    <span class="inline-block h-2 w-2 rounded-full" style="background-color: {{ $t['dot'] }};"></span>
                                    {{ $task->project?->name ?? $task->color }}
                                </span>
                                <span class="flex items-center gap-2">
                                    @if ($task->total_seconds_spent > 0)
                                        <span class="flex items-center gap-1">
                                            <x-heroicon-m-clock class="h-3 w-3" />
                                            {{ $fmt($task->total_seconds_spent) }}
                                        </span>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="$dispatch('start-pomodoro', { taskId: {{ $task->id }} })"
                                        class="flex items-center gap-0.5 rounded px-1 py-0.5 text-gray-500 hover:bg-white/60 hover:text-primary-600"
                                        title="Start Pomodoro"
                                    >
                                        <x-heroicon-m-play class="h-3.5 w-3.5" />
                                        Start
                                    </button>
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Create / edit modal --}}
    @if ($showTaskModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            wire:click.self="closeTaskModal"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $editingTaskId ? 'Edit task' : 'New task' }}
                </h2>

                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input
                            type="text"
                            wire:model="taskForm.name"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:bg-gray-800 dark:border-gray-700"
                        />
                        @error('taskForm.name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Project</label>
                        <select
                            wire:model="taskForm.color"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:bg-gray-800 dark:border-gray-700"
                        >
                            @foreach ($this->getProjects() as $project)
                                <option value="{{ $project->color }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                        @error('taskForm.color') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Column</label>
                        <select
                            wire:model="taskForm.board_column_id"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:bg-gray-800 dark:border-gray-700"
                        >
                            @foreach ($this->getBoardColumns() as $column)
                                <option value="{{ $column->id }}">{{ $column->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea
                            wire:model="taskForm.description"
                            rows="2"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:bg-gray-800 dark:border-gray-700"
                        ></textarea>
                    </div>

                    {{-- Subtasks (only when editing an existing task) --}}
                    @if ($editingTaskId)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Subtasks</label>
                            <div class="space-y-1">
                                @foreach ($this->getEditingSubtasks() as $subTask)
                                    <div class="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            @checked($subTask->finished)
                                            wire:click="toggleSubtask({{ $subTask->id }})"
                                            class="h-4 w-4 rounded border-gray-300 text-primary-600"
                                        />
                                        <span @class(['flex-1', 'line-through opacity-60' => $subTask->finished])>{{ $subTask->name }}</span>
                                        <button type="button" wire:click="deleteSubtask({{ $subTask->id }})" class="text-gray-400 hover:text-red-600">
                                            <x-heroicon-m-x-mark class="h-4 w-4" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-2 flex gap-2">
                                <input
                                    type="text"
                                    wire:model="taskForm.new_subtask"
                                    wire:keydown.enter.prevent="addSubtask"
                                    placeholder="Add subtask…"
                                    class="flex-1 rounded-lg border-gray-300 text-sm shadow-sm dark:bg-gray-800 dark:border-gray-700"
                                />
                                <button type="button" wire:click="addSubtask" class="rounded-lg bg-gray-100 px-3 text-sm dark:bg-gray-800">Add</button>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-5 flex items-center justify-between">
                    <div>
                        @if ($editingTaskId)
                            <button
                                type="button"
                                wire:click="deleteTask({{ $editingTaskId }})"
                                wire:confirm="Delete this task?"
                                class="text-sm text-red-600 hover:underline"
                            >Delete</button>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="closeTaskModal" class="rounded-lg px-4 py-2 text-sm text-gray-600 dark:text-gray-300">Cancel</button>
                        <button type="button" wire:click="saveTask" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">Save</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @assets
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    @endassets

    @script
    <script>
        const root = $wire.$el;

        const initSortables = () => {
            if (! window.Sortable || ! document.body.contains(root)) {
                return;
            }

            root.querySelectorAll('[data-column-id]').forEach((el) => {
                const existing = window.Sortable.get(el);
                if (existing) {
                    existing.destroy();
                }

                window.Sortable.create(el, {
                    group: 'tasks',
                    animation: 150,
                    draggable: '[data-task-id]',
                    ghostClass: 'opacity-40',
                    onEnd: (evt) => {
                        const columnId = parseInt(evt.to.dataset.columnId);
                        const ids = Array.from(evt.to.querySelectorAll('[data-task-id]'))
                            .map((n) => parseInt(n.dataset.taskId));
                        const taskId = parseInt(evt.item.dataset.taskId);
                        $wire.moveTask(taskId, columnId, ids);
                    },
                });
            });
        };

        const ready = () => window.Sortable ? initSortables() : setTimeout(ready, 50);
        ready();

        // Re-bind after Livewire DOM updates (scoped to this page's columns).
        Livewire.hook('morphed', () => initSortables());
    </script>
    @endscript
</x-filament-panels::page>
