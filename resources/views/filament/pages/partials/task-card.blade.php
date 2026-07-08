@php
    use App\Support\Format;

    /** @var \App\Models\Task $task */
    $t = $tint($task->color);
    $doneCount = $task->subTasks->where('finished', true)->count();
    $totalSubs = $task->subTasks->count();
    $isRunning = $runningTaskId === $task->id;
    $isSelected = ($selectedTaskId ?? null) === $task->id;
    // Only show a project label when it adds information. A project named after
    // the card's colour (e.g. "Red" on a red card) is redundant, so hide it.
    $projectName = $task->project?->name;
    $showProject = $projectName !== null
        && \Illuminate\Support\Str::lower($projectName) !== \Illuminate\Support\Str::lower((string) $task->color);

    // Build the card's inline style: full tint background, plus either a dashed
    // "break line" border in the accent colour while running, or a solid left
    // accent bar when idle. A focus ring marks the card open in the detail modal.
    $cardStyle = "background-color: {$t['bg']}; color: {$t['text']};";
    if ($isRunning) {
        // Filament's compiled CSS omits the `border-dashed` utility, so the
        // dashed "break line" border must be inlined to actually render.
        $cardStyle .= " border: 3px dashed {$t['dot']};";
    } else {
        $cardStyle .= " border-left: 4px solid {$t['dot']};";
    }
    if ($isSelected) {
        $cardStyle .= " box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px {$t['dot']}, 0 6px 14px -6px rgba(0,0,0,0.35);";
    }
@endphp

<div
    data-task-id="{{ $task->id }}"
    wire:key="task-{{ $task->id }}"
    wire:click="viewTask({{ $task->id }})"
    @contextmenu.prevent="$dispatch('open-task-menu', { id: {{ $task->id }}, x: $event.clientX, y: $event.clientY })"
    @class([
        'group relative cursor-grab rounded-md text-[13px] shadow-sm transition active:cursor-grabbing',
        'border border-transparent' => ! $isRunning,
        'border-2 border-dashed' => $isRunning,
    ])
    style="padding: 11px 13px; {{ $cardStyle }}"
>
    <div class="flex items-start justify-between gap-2">
        <span class="flex-1 text-left font-bold leading-snug">
            {{ $task->name }}
        </span>
        <button
            type="button"
            @click.stop="$dispatch('confirm-delete-task', { id: {{ $task->id }} })"
            class="-mr-1 mt-0.5 text-gray-500/70 opacity-0 transition group-hover:opacity-100 hover:text-red-600"
            title="Delete task"
        >
            <x-heroicon-m-trash class="h-3.5 w-3.5" />
        </button>
    </div>

    {{-- Meta icon row --}}
    <div class="mt-1.5 flex items-center gap-3 text-[11px] opacity-70">
            @if ($totalSubs > 0)
                <span class="flex flex-shrink-0 items-center gap-1 whitespace-nowrap" title="Subtasks done">
                    <x-heroicon-m-list-bullet class="h-3.5 w-3.5" />
                    {{ $doneCount }}/{{ $totalSubs }}
                </span>
            @endif
            @if ($task->total_seconds_spent > 0 || $isRunning)
                <span class="flex flex-shrink-0 items-center gap-1 whitespace-nowrap" title="Time spent">
                    <x-heroicon-m-clock class="h-3.5 w-3.5 flex-shrink-0" />
                    {{ Format::seconds($task->total_seconds_spent) }}
                    @if ($isRunning)
                        <span
                            x-data="{
                                start: '{{ $runningStartedAt }}',
                                mins: 0,
                                timer: null,
                                init() { this.calc(); this.timer = setInterval(() => this.calc(), 30000); },
                                destroy() { clearInterval(this.timer); },
                                calc() { this.mins = Math.floor(Math.max(0, (Date.now() - new Date(this.start).getTime()) / 60000)); },
                            }"
                            class="font-medium"
                            style="color: #dc2626;"
                            x-text="'+ ' + mins + 'm'"
                        ></span>
                    @endif
                </span>
            @endif
            @if ($showProject)
                <span class="ml-auto flex min-w-0 items-center gap-1" title="Project">
                    <span class="inline-block h-2 w-2 flex-shrink-0 rounded-full" style="background-color: {{ $t['dot'] }};"></span>
                    <span class="truncate">{{ $projectName }}</span>
                </span>
            @endif
        </div>

    {{-- Footer: subtasks --}}
    @if ($task->subTasks->isNotEmpty())
        <div
            class="mt-2 space-y-1 pt-2"
            style="border-top: 1px dashed {{ $t['dot'] }}66;"
        >
            @foreach ($task->subTasks as $subTask)
                {{-- Row click opens the detail modal (bubbles to the card); only the checkbox toggles. --}}
                <div class="flex items-center gap-1.5 text-[11px]">
                    <input
                        type="checkbox"
                        @checked($subTask->finished)
                        wire:click.stop="toggleSubtask({{ $subTask->id }})"
                        class="h-3.5 w-3.5 flex-shrink-0 rounded border-gray-400/60"
                        style="accent-color: {{ $t['dot'] }};"
                    />
                    <span @class(['cursor-pointer truncate', 'line-through opacity-50' => $subTask->finished])>{{ $subTask->name }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
