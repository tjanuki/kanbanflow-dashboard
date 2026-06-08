@php
    use App\Support\Format;

    /** @var \App\Models\Task $task */
    $t = $tint($task->color);
    $doneCount = $task->subTasks->where('finished', true)->count();
    $totalSubs = $task->subTasks->count();
    $projectName = $task->project?->name ?? $task->color;
    $createdLabel = $task->date
        ? ($task->date->isToday() ? 'Today' : $task->date->format('D, j M Y'))
        : optional($task->created_at)->format('D, j M Y');
@endphp

<div
    class="fixed inset-0 flex items-center justify-center p-4"
    style="z-index: 55; background-color: rgba(0, 0, 0, 0.45);"
    wire:click.self="closeDetailModal"
    wire:key="detail-{{ $task->id }}"
>
    {{-- Nudged +140px right of centre so the docked Pomodoro panel (see
         pomodoro-timer) sits to its left as a centred side-by-side group. --}}
    <div
        class="flex w-full max-w-2xl overflow-hidden bg-white dark:bg-gray-900"
        style="border-radius: 0.75rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); transform: translateX(140px);"
    >
        {{-- Main content --}}
        <div class="min-w-0 flex-1 p-5">
            {{-- Color band + title --}}
            <div class="-mx-5 -mt-5 mb-4 h-1.5" style="background-color: {{ $t['dot'] }};"></div>

            <div class="mb-3 flex items-start justify-between gap-3">
                <h2 class="text-lg font-semibold leading-snug text-gray-900 dark:text-gray-100">{{ $task->name }}</h2>
                <button type="button" wire:click="closeDetailModal" class="mt-1 flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <x-heroicon-m-x-mark class="h-5 w-5" />
                </button>
            </div>

            <p class="mb-4 text-xs text-gray-400">Created: {{ $createdLabel }}</p>

            {{-- Meta grid --}}
            <dl class="mb-4 space-y-2.5 text-sm">
                <div class="flex items-center gap-3">
                    <dt class="w-24 flex-shrink-0 text-gray-400">Color</dt>
                    <dd class="flex items-center gap-2 text-gray-700 dark:text-gray-200">
                        <span class="inline-block h-3 w-3 rounded-full" style="background-color: {{ $t['dot'] }};"></span>
                        {{ $projectName }}
                    </dd>
                </div>
                <div class="flex items-center gap-3">
                    <dt class="w-24 flex-shrink-0 text-gray-400">Members</dt>
                    <dd class="flex items-center gap-1.5">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-[10px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-200">ME</span>
                    </dd>
                </div>
                <div class="flex items-center gap-3">
                    <dt class="w-24 flex-shrink-0 text-gray-400">Time spent</dt>
                    <dd class="text-gray-700 dark:text-gray-200">{{ Format::seconds($task->total_seconds_spent) }}</dd>
                </div>
            </dl>

            {{-- Subtasks --}}
            <div>
                <h3 class="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    Subtasks <span class="text-gray-400">{{ $doneCount }}/{{ $totalSubs }}</span>
                </h3>
                <div class="space-y-1">
                    @foreach ($task->subTasks as $subTask)
                        <div class="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                @checked($subTask->finished)
                                wire:click="toggleSubtask({{ $subTask->id }})"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600"
                            />
                            <span @class(['flex-1 truncate', 'line-through opacity-60' => $subTask->finished])>{{ $subTask->name }}</span>
                            <button type="button" wire:click="deleteSubtask({{ $subTask->id }})" class="text-gray-400 hover:text-red-600">
                                <x-heroicon-m-x-mark class="h-4 w-4" />
                            </button>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-2">
                    <input
                        type="text"
                        wire:model="newSubtask"
                        wire:keydown.enter.prevent="addDetailSubtask"
                        placeholder="Add subtask…"
                        class="flex-1 rounded-lg border-gray-300 text-sm shadow-sm dark:bg-gray-800 dark:border-gray-700"
                    />
                    <button type="button" wire:click="addDetailSubtask" class="rounded-lg bg-gray-100 px-3 text-sm dark:bg-gray-800">Add</button>
                </div>
            </div>
        </div>

        {{-- Action rail --}}
        <div class="flex w-14 flex-col items-center gap-1 border-l border-gray-100 bg-gray-50 py-3 dark:border-gray-800 dark:bg-gray-800/50">
            @php
                $railBtn = 'flex h-10 w-10 flex-col items-center justify-center rounded-lg text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-700';
            @endphp

            {{-- Add subtask (focus the field) --}}
            <button type="button" class="{{ $railBtn }}" title="Add subtask"
                x-on:click="$el.closest('.fixed').querySelector('input[type=text]')?.focus()">
                <x-heroicon-m-plus class="h-5 w-5" />
            </button>

            {{-- Move (column dropdown) --}}
            <div x-data="{ open: false }" class="relative">
                <button type="button" class="{{ $railBtn }}" title="Move" x-on:click="open = ! open">
                    <x-heroicon-m-arrows-right-left class="h-5 w-5" />
                </button>
                <div
                    x-show="open"
                    x-on:click.outside="open = false"
                    x-transition
                    class="absolute right-12 top-0 z-10 w-44 rounded-lg border border-gray-200 bg-white py-1 shadow-xl dark:border-gray-700 dark:bg-gray-900"
                    style="display: none;"
                >
                    @foreach ($this->getBoardColumns() as $column)
                        <button
                            type="button"
                            wire:click="moveViewingTask({{ $column->id }})"
                            x-on:click="open = false"
                            @class([
                                'flex w-full items-center justify-between px-3 py-1.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800',
                                'font-semibold text-primary-600' => $column->id === $task->board_column_id,
                                'text-gray-700 dark:text-gray-200' => $column->id !== $task->board_column_id,
                            ])
                        >
                            {{ $column->name }}
                            @if ($column->id === $task->board_column_id)
                                <x-heroicon-m-check class="h-4 w-4" />
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Timer --}}
            <button
                type="button"
                wire:click="$dispatch('start-pomodoro', { taskId: {{ $task->id }} })"
                class="{{ $railBtn }} hover:!bg-red-100 hover:!text-red-600"
                title="Start timer"
            >
                <x-heroicon-m-play class="h-5 w-5" />
            </button>

            {{-- Reports (stub) --}}
            <button type="button" class="{{ $railBtn }}" title="Reports (coming soon)">
                <x-heroicon-m-chart-bar class="h-5 w-5" />
            </button>

            {{-- Edit (opens the form) --}}
            <button type="button" wire:click="editFromDetail" class="{{ $railBtn }}" title="Edit">
                <x-heroicon-m-pencil-square class="h-5 w-5" />
            </button>

            <div class="my-1 h-px w-6 bg-gray-200 dark:bg-gray-700"></div>

            {{-- Delete --}}
            <button
                type="button"
                wire:click="deleteTask({{ $task->id }})"
                wire:confirm="Delete this task?"
                class="{{ $railBtn }} hover:!bg-red-100 hover:!text-red-600"
                title="Delete"
            >
                <x-heroicon-m-trash class="h-5 w-5" />
            </button>
        </div>
    </div>
</div>
