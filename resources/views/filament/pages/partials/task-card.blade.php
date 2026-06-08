@php
    use App\Support\Format;

    /** @var \App\Models\Task $task */
    $t = $tint($task->color);
    $doneCount = $task->subTasks->where('finished', true)->count();
    $totalSubs = $task->subTasks->count();
    $isRunning = $runningTaskId === $task->id;
    $projectName = $task->project?->name ?? $task->color;
    $initials = \Illuminate\Support\Str::of($projectName)->explode(' ')
        ->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))
        ->take(2)->implode('');
@endphp

<div
    data-task-id="{{ $task->id }}"
    wire:key="task-{{ $task->id }}"
    @class([
        'group relative cursor-grab rounded-md text-[13px] shadow-sm active:cursor-grabbing',
        'border border-transparent' => ! $isRunning,
        'border-2 border-dashed' => $isRunning,
    ])
    style="padding: 12px 14px; background-color: {{ $t['bg'] }}; color: {{ $t['text'] }};@if ($isRunning) border-color: {{ $t['dot'] }};@endif"
>
    {{-- Running member badge --}}
    @if ($isRunning)
        <span
            class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full text-[9px] font-bold uppercase text-white shadow"
            style="background-color: {{ $t['dot'] }};"
            title="Running"
        >{{ $initials ?: 'ME' }}</span>
    @endif

    <div class="flex items-start justify-between gap-2">
        <button
            type="button"
            wire:click="viewTask({{ $task->id }})"
            class="flex-1 text-left font-medium leading-snug"
        >
            {{ $task->name }}
        </button>
        <button
            type="button"
            wire:click="deleteTask({{ $task->id }})"
            wire:confirm="Delete this task?"
            class="-mr-1 mt-0.5 text-gray-500/70 opacity-0 transition group-hover:opacity-100 hover:text-red-600"
            title="Delete task"
        >
            <x-heroicon-m-trash class="h-3.5 w-3.5" />
        </button>
    </div>

    {{-- Meta icon row --}}
    @if ($totalSubs > 0 || $task->total_seconds_spent > 0 || $isRunning)
        <div class="mt-1.5 flex items-center gap-3 text-[11px] opacity-70">
            @if ($totalSubs > 0)
                <span class="flex items-center gap-1" title="Subtasks done">
                    <x-heroicon-m-list-bullet class="h-3.5 w-3.5" />
                    {{ $doneCount }}/{{ $totalSubs }}
                </span>
            @endif
            @if ($task->total_seconds_spent > 0 || $isRunning)
                <span class="flex items-center gap-1" title="Time spent">
                    <x-heroicon-m-clock class="h-3.5 w-3.5" />
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
        </div>
    @endif

    {{-- Subtasks --}}
    @if ($task->subTasks->isNotEmpty())
        <div class="mt-1.5 space-y-0.5">
            @foreach ($task->subTasks as $subTask)
                <label class="flex items-center gap-1.5 text-[11px]">
                    <input
                        type="checkbox"
                        @checked($subTask->finished)
                        wire:click="toggleSubtask({{ $subTask->id }})"
                        class="h-3 w-3 rounded border-gray-400/60 text-primary-600"
                        style="accent-color: {{ $t['dot'] }};"
                    />
                    <span @class(['truncate', 'line-through opacity-50' => $subTask->finished])>{{ $subTask->name }}</span>
                </label>
            @endforeach
        </div>
    @endif

    {{-- Footer: project + start timer --}}
    <div class="mt-1.5 flex items-center justify-between text-[11px] opacity-70">
        <span class="flex min-w-0 items-center gap-1">
            <span class="inline-block h-2 w-2 flex-shrink-0 rounded-full" style="background-color: {{ $t['dot'] }};"></span>
            <span class="truncate">{{ $projectName }}</span>
        </span>
        @unless ($isRunning)
            <button
                type="button"
                wire:click="$dispatch('start-pomodoro', { taskId: {{ $task->id }} })"
                class="flex items-center gap-0.5 rounded px-1 py-0.5 hover:bg-white/50 hover:text-gray-900"
                title="Start Pomodoro"
            >
                <x-heroicon-m-play class="h-3.5 w-3.5" />
                Start
            </button>
        @endunless
    </div>
</div>
