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
        <div class="min-w-0 flex-1" style="padding: 1.25rem;">
            {{-- Color band + title --}}
            <div class="h-1.5" style="margin: -1.25rem -1.25rem 1rem; background-color: {{ $t['dot'] }};"></div>

            <div
                class="mb-3 flex items-start justify-between gap-3"
                x-data="{ editing: false }"
            >
                {{-- Click the title to edit; save on blur or Enter, cancel on Escape. --}}
                <h2
                    x-show="! editing"
                    x-on:click="editing = true; $nextTick(() => { $refs.titleInput.focus(); $refs.titleInput.select(); })"
                    class="min-w-0 flex-1 cursor-text rounded px-1 -mx-1 text-lg font-semibold leading-snug text-gray-900 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-800"
                    title="Click to edit"
                >{{ $task->name }}</h2>
                <input
                    x-ref="titleInput"
                    x-show="editing"
                    x-cloak
                    type="text"
                    value="{{ $task->name }}"
                    x-on:blur="editing = false; $wire.renameViewingTask($el.value)"
                    x-on:keydown.enter.prevent="$el.blur()"
                    x-on:keydown.escape.prevent="$el.value = @js($task->name); editing = false"
                    class="min-w-0 flex-1 rounded-md border-gray-300 bg-white px-1.5 py-0.5 text-lg font-semibold leading-snug text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                />
                <button type="button" wire:click="closeDetailModal" class="mt-1 flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <x-heroicon-m-x-mark class="h-5 w-5" />
                </button>
            </div>

            <p class="mb-4 text-xs text-gray-400">Created: {{ $createdLabel }}</p>

            {{-- Meta grid --}}
            <dl class="mb-4 space-y-2.5 text-sm">
                <div class="flex items-center gap-3">
                    <dt class="w-24 flex-shrink-0 text-gray-400">Color</dt>
                    <dd class="relative" x-data="{ open: false }">
                        {{-- Trigger: dot + label, click to open the colour list. --}}
                        <button
                            type="button"
                            x-on:click="open = ! open"
                            class="flex items-center gap-2 rounded-md px-1.5 py-1 text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            <span class="inline-block h-3 w-3 flex-shrink-0 rounded-full" style="background-color: {{ $t['dot'] }};"></span>
                            <span>{{ $projectName }}</span>
                            <x-heroicon-m-chevron-up-down class="h-4 w-4 flex-shrink-0 text-gray-400" />
                        </button>

                        {{-- Colour list (inline-styled position/z-index/scroll: Filament
                             panel CSS omits arbitrary z-[…]/max-h-[…] utilities). --}}
                        <div
                            x-show="open"
                            x-on:click.outside="open = false"
                            x-transition
                            class="border border-gray-200 bg-white py-1 dark:border-gray-700 dark:bg-gray-900"
                            style="display: none; position: absolute; left: 0; top: 100%; margin-top: 4px; z-index: 70; width: 14rem; max-height: 16rem; overflow-y: auto; border-radius: 0.5rem; box-shadow: 0 12px 32px -8px rgba(0,0,0,0.30), 0 0 0 1px rgba(0,0,0,0.04);"
                        >
                            @foreach ($this->getProjects() as $project)
                                @php $selected = $project->color === $task->color; @endphp
                                <button
                                    type="button"
                                    wire:click="setViewingTaskColor('{{ $project->color }}')"
                                    x-on:click="open = false"
                                    @class([
                                        'flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800',
                                        'font-semibold text-gray-900 dark:text-gray-100' => $selected,
                                        'text-gray-700 dark:text-gray-200' => ! $selected,
                                    ])
                                >
                                    <span class="inline-block h-3 w-3 flex-shrink-0 rounded-full" style="background-color: {{ \App\Support\Palette::tint($project->color)['dot'] }};"></span>
                                    <span class="flex-1 truncate">{{ $project->name }}</span>
                                    @if ($selected)
                                        <x-heroicon-m-check class="h-4 w-4 flex-shrink-0 text-primary-600" />
                                    @endif
                                </button>
                            @endforeach
                        </div>
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
        <div class="flex w-14 flex-col items-center gap-1 border-l border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50" style="padding: 0.75rem 0;">
            @php
                $railBtn = 'flex h-10 w-10 flex-col items-center justify-center rounded-lg text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-700';
            @endphp

            {{-- Timer: pick Pomodoro or Stopwatch from a small menu --}}
            <div
                class="relative"
                x-data="{
                    open: false,
                    menuX: 0,
                    menuY: 0,
                    toggle() {
                        if (this.open) { this.open = false; return; }
                        const r = $refs.timerTrigger.getBoundingClientRect();
                        this.menuX = r.right + 8;   // just right of the play icon
                        this.menuY = r.top - 4;     // align with the icon, minus the menu's padding
                        this.open = true;
                    },
                }"
                x-on:click.outside="open = false"
                @keydown.escape.window="open = false"
            >
                <button
                    type="button"
                    x-ref="timerTrigger"
                    @click="toggle()"
                    class="{{ $railBtn }} hover:!bg-red-100 hover:!text-red-600"
                    :class="open && '!bg-red-100 !text-red-600'"
                    title="Start timer"
                >
                    <x-heroicon-m-play class="h-5 w-5" />
                </button>

                {{-- Teleported to <body> so it escapes the modal's overflow/transform
                     clipping and can open to the right of the play icon. --}}
                <template x-teleport="body">
                    <div
                        x-show="open"
                        x-transition.opacity.duration.100ms
                        @click.outside="open = false"
                        class="border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
                        :style="`position: fixed; left: ${menuX}px; top: ${menuY}px; z-index: 9999; width: 12rem; border-radius: 12px; padding: 4px; box-shadow: 0 12px 32px -8px rgba(0,0,0,0.30), 0 0 0 1px rgba(0,0,0,0.04);`"
                    >
                        <button
                            type="button"
                            @click="$dispatch('start-pomodoro', { taskId: {{ $task->id }}, mode: 'pomodoro' }); open = false"
                            class="flex w-full items-center gap-2 whitespace-nowrap px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                            style="border-radius: 8px;"
                        >
                            <x-heroicon-m-play class="h-4 w-4 flex-shrink-0 opacity-70" />
                            Start Pomodoro
                        </button>
                        <button
                            type="button"
                            @click="$dispatch('start-pomodoro', { taskId: {{ $task->id }}, mode: 'stopwatch' }); open = false"
                            class="flex w-full items-center gap-2 whitespace-nowrap px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                            style="border-radius: 8px;"
                        >
                            <x-heroicon-m-clock class="h-4 w-4 flex-shrink-0 opacity-70" />
                            Start Stopwatch
                        </button>
                    </div>
                </template>
            </div>

            {{-- Reports (task history) --}}
            <button type="button" wire:click="openTaskHistory" class="{{ $railBtn }}" title="Reports">
                <x-heroicon-m-chart-bar class="h-5 w-5" />
            </button>

            <div class="my-1 h-px w-6 bg-gray-200 dark:bg-gray-700"></div>

            {{-- Delete --}}
            <button
                type="button"
                @click="$dispatch('confirm-delete-task', { id: {{ $task->id }} })"
                class="{{ $railBtn }} hover:!bg-red-100 hover:!text-red-600"
                title="Delete"
            >
                <x-heroicon-m-trash class="h-5 w-5" />
            </button>
        </div>
    </div>
</div>
