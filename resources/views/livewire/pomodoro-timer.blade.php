@php
    use App\Support\Format;

    // Reusable Alpine countdown definition. `start` is an ISO string or null.
    $clock = fn (?string $start, int $work) => "{
        startedAt: " . ($start ? "'{$start}'" : 'null') . ",
        work: {$work},
        now: Math.floor(Date.now() / 1000),
        timer: null,
        init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
        destroy() { clearInterval(this.timer); },
        tick() { this.now = Math.floor(Date.now() / 1000); },
        get elapsed() {
            if (! this.startedAt) return 0;
            return Math.max(0, this.now - Math.floor(new Date(this.startedAt).getTime() / 1000));
        },
        get remaining() { return Math.max(0, this.work - this.elapsed); },
        fmt(s) {
            const m = Math.floor(s / 60).toString().padStart(2, '0');
            const sec = (s % 60).toString().padStart(2, '0');
            return m + ':' + sec;
        },
        get label() { return this.startedAt ? this.fmt(this.remaining) : this.fmt(this.work); },
    }";
@endphp

<div class="pointer-events-none fixed inset-0" style="z-index: 60;">
    {{-- The launcher now lives in the top bar (see PomodoroPill / USER_MENU_BEFORE). --}}

    {{-- Panel anchored top-right, directly under the top-bar timer icon. --}}
    @if ($showPanel)
        <div
            class="pointer-events-auto fixed flex w-72 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
            style="top: 4rem; right: 0.75rem; z-index: 60;"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between bg-gray-50 px-4 py-2 dark:bg-gray-800">
                <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <span style="color: #ef4444;"><x-heroicon-s-clock class="h-4 w-4" /></span>
                    Pomodoro
                </span>
                <button type="button" wire:click="togglePanel" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" title="Collapse">
                    <x-heroicon-m-chevron-up class="h-4 w-4" />
                </button>
            </div>

            {{-- Countdown --}}
            <div class="px-4 py-4 text-center">
                @if ($runningEntryId)
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Time until break</p>
                    <div
                        wire:key="clock-panel-{{ $runningEntryId }}"
                        x-data="{{ $clock($runningStartedAt, $workSeconds) }}"
                        class="my-1 font-mono text-4xl font-bold tabular-nums text-gray-900 dark:text-gray-100"
                    >
                        <span x-text="label"></span>
                    </div>
                    <button
                        type="button"
                        wire:click="stop"
                        class="mt-1 inline-flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-sm font-medium text-white transition hover:opacity-90"
                        style="background-color: #dc2626; color: #ffffff;"
                    >
                        <x-heroicon-m-stop class="h-4 w-4" />
                        Stop
                    </button>

                    {{-- Running-task pill --}}
                    <div class="mt-3 flex items-center justify-center">
                        <span class="inline-flex max-w-full items-center gap-1.5 truncate rounded-full px-3 py-1 text-xs font-medium" style="background-color: #fef2f2; color: #b91c1c;">
                            <span class="h-1.5 w-1.5 rounded-full" style="background-color: #ef4444;"></span>
                            <span class="truncate">{{ $runningTaskName }}</span>
                        </span>
                    </div>

                    {{-- Switch the timer onto whichever task's detail modal is open. --}}
                    @if ($openTaskId && $openTaskId !== $runningTaskId)
                        <div class="mt-1.5 text-center">
                            <button
                                type="button"
                                wire:click="switchToOpenTask"
                                class="text-xs font-medium text-blue-600 underline underline-offset-2 hover:text-blue-700 dark:text-blue-400"
                                title="{{ $openTaskName }}"
                            >
                                Select open task
                            </button>
                        </div>
                    @endif
                @else
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Time until break</p>
                    <div class="my-1 font-mono text-4xl font-bold tabular-nums text-gray-300 dark:text-gray-600">25:00</div>
                    <p class="mt-1 text-xs text-gray-400">Press ▶ on a task to start.</p>
                @endif
            </div>

            {{-- TODAY summary --}}
            <div class="flex items-center justify-center gap-2 border-t border-gray-100 px-4 py-2 text-xs dark:border-gray-800">
                <span class="font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Today</span>
                <span class="text-gray-700 dark:text-gray-200">{{ Format::seconds($this->todaySeconds) }}</span>
                <span class="text-gray-300">·</span>
                <span class="text-gray-700 dark:text-gray-200">{{ $this->todayPomodoros }} {{ \Illuminate\Support\Str::plural('Pomodoro', $this->todayPomodoros) }}</span>
            </div>

            {{-- Session log --}}
            <div class="max-h-44 overflow-y-auto border-t border-gray-100 px-4 py-2 dark:border-gray-800">
                @forelse ($this->todayEntries as $entry)
                    <div class="flex items-center justify-between gap-2 py-1 text-xs">
                        <span class="min-w-0 flex-1 truncate text-gray-600 dark:text-gray-300">{{ $entry->task?->name ?? '—' }}</span>
                        <span class="whitespace-nowrap font-mono text-[11px] text-gray-400">
                            {{ $entry->started_at->format('g:i A') }}
                            @if ($entry->ended_at)
                                — {{ $entry->ended_at->format('g:i A') }}
                            @else
                                — <span style="color: #ef4444;">pending</span>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="py-3 text-center text-xs text-gray-400">No sessions yet today.</p>
                @endforelse
            </div>

            {{-- Footer toolbar (Stopwatch / Add time / Log / Settings — stubs for a later pass) --}}
            <div class="flex items-center justify-around border-t border-gray-100 bg-gray-50 px-2 py-1.5 dark:border-gray-800 dark:bg-gray-800">
                {{-- TODO: wire Stopwatch to a type=stopwatch time entry. --}}
                <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700" title="Stopwatch (coming soon)">
                    <x-heroicon-m-play-pause class="h-4 w-4" />
                </button>
                {{-- TODO: manual "Add time" entry. --}}
                <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700" title="Add time (coming soon)">
                    <x-heroicon-m-plus-circle class="h-4 w-4" />
                </button>
                {{-- TODO: full time log view. --}}
                <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700" title="Log (coming soon)">
                    <x-heroicon-m-list-bullet class="h-4 w-4" />
                </button>
                {{-- TODO: timer settings (work/break length). --}}
                <button type="button" class="rounded p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700" title="Settings (coming soon)">
                    <x-heroicon-m-cog-6-tooth class="h-4 w-4" />
                </button>
            </div>
        </div>
    @endif
</div>
