<x-filament-panels::page>
    @php
        // KanbanFlow colour names -> stronger full-tint background / accent / text.
        // Single source of truth lives in App\Support\Palette.
        $tint = fn ($color) => \App\Support\Palette::tint($color);

        $runningEntry = $this->getRunningEntry();
        $runningTaskId = $runningEntry?->task_id;
        $runningStartedAt = $runningEntry?->started_at?->toIso8601String();
    @endphp

    <div
        x-data="{
            taskId: null,
            x: 0,
            y: 0,
            submenu: false,
            submenuTimer: null,
            open(e) {
                this.taskId = e.detail.id;
                this.submenu = false;
                const w = 208, h = 240;
                this.x = Math.min(e.detail.x, window.innerWidth - w - 8);
                this.y = Math.min(e.detail.y, window.innerHeight - h - 8);
            },
            openSubmenu() { clearTimeout(this.submenuTimer); this.submenu = true; },
            closeSubmenu() { this.submenuTimer = setTimeout(() => { this.submenu = false; }, 220); },
            close() { clearTimeout(this.submenuTimer); this.taskId = null; this.submenu = false; },
        }"
        @open-task-menu.window="open($event)"
    >
    <div class="flex gap-px overflow-x-auto pb-4">
        @foreach ($this->getBoardColumns() as $column)
            @php $isDay = $column->type === 'day'; @endphp
            <div
                @class([
                    'flex flex-col',
                    'rounded-xl bg-gray-100 dark:bg-gray-800/60' => ! $isDay,
                    'border-l border-gray-200 dark:border-gray-700' => $isDay && ! $loop->first,
                ])
                style="width: 16rem; min-width: 16rem; flex: none;"
            >
                {{-- Column header --}}
                <div @class([
                    'sticky top-0 z-10 flex items-center justify-between px-3 py-2',
                    'rounded-t-xl bg-gray-100 dark:bg-gray-800/60' => ! $isDay,
                    'bg-white dark:bg-gray-900' => $isDay,
                ])>
                    <span class="w-6"></span>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $column->name }}</span>
                        @if ($column->wip_limit)
                            @php $overLimit = $column->tasks->count() > $column->wip_limit; @endphp
                            <span
                                class="rounded-full px-2 text-xs font-medium"
                                style="{{ $overLimit ? 'background-color:#fee2e2;color:#dc2626;' : 'background-color:#e5e7eb;color:#6b7280;' }}"
                            >
                                {{ $column->tasks->count() }} / {{ $column->wip_limit }}
                            </span>
                        @else
                            <span class="rounded-full bg-gray-200 px-2 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                {{ $column->tasks->count() }}
                            </span>
                        @endif
                    </div>
                    <button
                        type="button"
                        wire:click="newTask({{ $column->id }})"
                        class="flex h-6 w-6 items-center justify-center rounded-full shadow-sm transition hover:opacity-90"
                        style="background-color: #22c55e; color: #ffffff;"
                        title="Add task"
                    >
                        <x-heroicon-m-plus class="h-4 w-4" />
                    </button>
                </div>

                {{-- Droppable task list --}}
                <div
                    data-column-id="{{ $column->id }}"
                    class="flex min-h-[60px] flex-col gap-1.5 px-2 pb-3 pt-1"
                >
                    @foreach ($column->tasks as $task)
                        @include('filament.pages.partials.task-card', [
                            'task' => $task,
                            'tint' => $tint,
                            'runningTaskId' => $runningTaskId,
                            'runningStartedAt' => $runningStartedAt,
                            'selectedTaskId' => $this->viewingTaskId,
                        ])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Right-click context menu (shared by every card; teleported coords set on open) --}}
    <div
        x-show="taskId !== null"
        @click.outside="close()"
        @keydown.escape.window="close()"
        x-transition.opacity.duration.100ms
        class="fixed w-52 border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
        :style="`left: ${x}px; top: ${y}px; z-index: 9999; border-radius: 12px; padding: 4px; box-shadow: 0 12px 32px -8px rgba(0,0,0,0.30), 0 0 0 1px rgba(0,0,0,0.04);`"
        style="display: none;"
    >
        {{-- Move (opens a submenu) --}}
        <div class="relative" @mouseenter="openSubmenu()" @mouseleave="closeSubmenu()">
            <button
                type="button"
                @click="submenu ? closeSubmenu() : openSubmenu()"
                class="flex w-full items-center gap-2 whitespace-nowrap px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                :class="submenu && 'bg-gray-100 dark:bg-gray-800'"
                style="border-radius: 8px;"
            >
                <x-heroicon-m-arrows-pointing-out class="h-4 w-4 flex-shrink-0 opacity-70" />
                <span class="flex-1">Move</span>
                <x-heroicon-m-chevron-right class="h-4 w-4 flex-shrink-0 opacity-50" />
            </button>

            {{-- Invisible bridge so the cursor can cross the gap without closing the submenu --}}
            <div
                x-show="submenu"
                @mouseenter="openSubmenu()"
                style="display: none; position: absolute; left: 100%; top: 0; width: 12px; height: 100%; z-index: 9999;"
            ></div>

            <div
                x-show="submenu"
                @mouseenter="openSubmenu()"
                @mouseleave="closeSubmenu()"
                x-transition.opacity.duration.100ms
                class="w-44 border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
                style="display: none; position: absolute; left: 100%; top: -4px; margin-left: 8px; z-index: 9999; border-radius: 12px; padding: 4px; box-shadow: 0 12px 32px -8px rgba(0,0,0,0.30), 0 0 0 1px rgba(0,0,0,0.04);"
            >
                <button
                    type="button"
                    @click="$wire.moveTaskRight(taskId); close()"
                    class="flex w-full items-center gap-2 whitespace-nowrap px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                    style="border-radius: 8px;"
                >
                    <x-heroicon-m-arrow-right class="h-4 w-4 flex-shrink-0 opacity-70" />
                    Move right
                </button>
                <button
                    type="button"
                    @click="$wire.moveTaskToTop(taskId); close()"
                    class="flex w-full items-center gap-2 whitespace-nowrap px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                    style="border-radius: 8px;"
                >
                    <x-heroicon-m-arrow-up class="h-4 w-4 flex-shrink-0 opacity-70" />
                    Move to top
                </button>
                <button
                    type="button"
                    @click="$wire.moveTaskToBottom(taskId); close()"
                    class="flex w-full items-center gap-2 whitespace-nowrap px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                    style="border-radius: 8px;"
                >
                    <x-heroicon-m-arrow-down class="h-4 w-4 flex-shrink-0 opacity-70" />
                    Move to bottom
                </button>
            </div>
        </div>

        {{-- Copy here --}}
        <button
            type="button"
            @mouseenter="closeSubmenu()"
            @click="window.captureTaskBoardPositions?.(); $wire.copyTask(taskId); close()"
            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
            style="border-radius: 8px;"
        >
            <x-heroicon-m-document-duplicate class="h-4 w-4 flex-shrink-0 opacity-70" />
            Copy here
        </button>

        {{-- Divider --}}
        <div class="my-1 border-t border-gray-100 dark:border-gray-800"></div>

        {{-- Delete --}}
        <button
            type="button"
            @mouseenter="closeSubmenu()"
            @click="$dispatch('confirm-delete-task', { id: taskId }); close()"
            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
            style="border-radius: 8px;"
        >
            <x-heroicon-m-trash class="h-4 w-4 flex-shrink-0 opacity-70" />
            Delete
        </button>
    </div>
    </div>

    {{-- Read-only detail modal (sits above the edit form) --}}
    @if ($this->getViewingTask())
        @include('filament.pages.partials.task-detail-modal', [
            'task' => $this->getViewingTask(),
            'tint' => $tint,
        ])

        {{-- Task history modal (Reports button on the detail rail) --}}
        @if ($showTaskHistory)
            @include('filament.pages.partials.task-history-modal', [
                'task' => $this->getViewingTask(),
            ])
        @endif
    @endif

    {{-- Create / edit form modal --}}
    @if ($showTaskModal)
        <div
            class="fixed inset-0 flex items-center justify-center p-4"
            style="z-index: 65; background-color: rgba(0, 0, 0, 0.45);"
            wire:click.self="closeTaskModal"
        >
            <div
                class="w-full max-w-md bg-white dark:bg-gray-900"
                style="border-radius: 0.75rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); padding: 1.25rem;"
            >
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
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->getProjects() as $project)
                                @php
                                    $pt = $tint($project->color);
                                    $isSelected = $taskForm['color'] === $project->color;
                                @endphp
                                <button
                                    type="button"
                                    wire:click="$set('taskForm.color', '{{ $project->color }}')"
                                    title="{{ $project->name }}"
                                    class="flex items-center gap-1.5 text-xs transition"
                                    style="padding: 4px 9px; border-radius: 9999px; border: 2px solid {{ $isSelected ? $pt['dot'] : '#e5e7eb' }}; background-color: {{ $isSelected ? $pt['bg'] : 'transparent' }}; color: {{ $isSelected ? $pt['text'] : '#6b7280' }};"
                                >
                                    <span class="inline-block h-3 w-3 flex-shrink-0 rounded-full" style="background-color: {{ $pt['dot'] }};"></span>
                                    {{ $project->name }}
                                </button>
                            @endforeach
                        </div>
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
                                @click="$dispatch('confirm-delete-task', { id: {{ $editingTaskId }} })"
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

    {{-- Custom delete confirmation dialog (replaces the native browser confirm) --}}
    <div
        x-data="{
            show: false,
            taskId: null,
            open(e) { this.taskId = e.detail.id; this.show = true; $nextTick(() => $refs.confirmBtn?.focus()); },
            confirm() { if (this.taskId !== null) { $wire.deleteTask(this.taskId); } this.cancel(); },
            cancel() { this.show = false; this.taskId = null; },
        }"
        @confirm-delete-task.window="open($event)"
        @keydown.escape.window="show && cancel()"
    >
        <div
            x-show="show"
            x-cloak
            x-transition.opacity.duration.150ms
            class="fixed inset-0 flex items-center justify-center p-4"
            style="z-index: 80; background-color: rgba(0, 0, 0, 0.45);"
            @click.self="cancel()"
        >
            <div
                class="w-full max-w-sm bg-white dark:bg-gray-900"
                style="border-radius: 0.75rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); padding: 1.25rem;"
                @click.stop
            >
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Delete task</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Are you sure you want to delete this task? This action cannot be undone.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        @click="cancel()"
                        class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    >Cancel</button>
                    <button
                        type="button"
                        x-ref="confirmBtn"
                        @click="confirm()"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-white"
                        style="background-color: #dc2626;"
                        @mouseenter="$el.style.backgroundColor = '#ef4444'"
                        @mouseleave="$el.style.backgroundColor = '#dc2626'"
                    >Delete</button>
                </div>
            </div>
        </div>
    </div>

    @assets
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
        <style>
            [x-cloak] { display: none !important; }
            @keyframes taskBlink {
                0% { opacity: 1; }
                50% { opacity: 0.2; }
                100% { opacity: 1; }
            }
            @keyframes taskAppear {
                from { opacity: 0; transform: translateY(-8px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .task-blink { animation: taskBlink 0.35s ease-in-out 0.4s 2; }
            /* Freshly copied card: fade/slide in, then flash twice. */
            .task-copied-in { animation: taskAppear 0.28s ease-out, taskBlink 0.35s ease-in-out 0.35s 2; }
        </style>
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

        // Snapshot card positions just before "Copy here" sends its request, so the
        // cards displaced by the new copy can slide (FLIP) to their new spots.
        let flipTops = null;
        window.captureTaskBoardPositions = () => {
            flipTops = new Map();
            root.querySelectorAll('[data-task-id]').forEach((el) => {
                flipTops.set(el.dataset.taskId, el.getBoundingClientRect().top);
            });
        };

        const runFlip = () => {
            if (! flipTops) {
                return;
            }
            const prev = flipTops;
            flipTops = null;

            root.querySelectorAll('[data-task-id]').forEach((el) => {
                const oldTop = prev.get(el.dataset.taskId);
                if (oldTop === undefined) {
                    return; // newly inserted card — handled by the fade-in animation
                }
                const delta = oldTop - el.getBoundingClientRect().top;
                if (! delta) {
                    return;
                }
                el.style.transition = 'none';
                el.style.transform = `translateY(${delta}px)`;
                requestAnimationFrame(() => {
                    el.style.transition = 'transform 0.3s ease';
                    el.style.transform = '';
                    el.addEventListener('transitionend', () => {
                        el.style.transition = '';
                        el.style.transform = '';
                    }, { once: true });
                });
            });
        };

        // Re-bind after Livewire DOM updates (scoped to this page's columns).
        Livewire.hook('morphed', () => {
            initSortables();
            runFlip();
        });

        // Fade the copied card in and flash it twice once the board re-renders with it.
        Livewire.on('task-copied', (event) => {
            const id = Array.isArray(event) ? event[0]?.id : event?.id;
            if (id == null) {
                return;
            }

            let tries = 0;
            const animate = () => {
                const el = root.querySelector(`[data-task-id="${id}"]`);
                if (el) {
                    el.classList.remove('task-copied-in');
                    void el.offsetWidth; // restart the animation if it's still applied
                    el.classList.add('task-copied-in');
                    el.addEventListener('animationend', () => el.classList.remove('task-copied-in'), { once: true });
                } else if (tries++ < 10) {
                    requestAnimationFrame(animate);
                }
            };
            requestAnimationFrame(animate);
        });
    </script>
    @endscript
</x-filament-panels::page>
