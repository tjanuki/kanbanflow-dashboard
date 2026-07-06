@php
    use App\Support\Format;
    use App\Support\Palette;

    /** @var \App\Models\Task $task */
    $dot = Palette::tint($task->color)['dot'];

    // Matches PomodoroTimer::$workSeconds — a pomodoro at/over this ran its
    // full work interval and is labelled "Successful Pomodoro" in the log.
    $workSeconds = 1500;
@endphp

{{-- Task history modal (Reports button on the detail-modal rail).
     Same layout as the Timer log modal, scoped to one task. --}}
<div
    data-testid="task-history-modal"
    class="pointer-events-auto fixed inset-0 flex items-start justify-center overflow-y-auto"
    style="z-index: 90; background-color: rgba(0, 0, 0, 0.45); padding: 24px 16px; overscroll-behavior: contain;"
    wire:click.self="closeTaskHistory"
    x-on:keydown.escape.window.stop="$wire.closeTaskHistory()"
    {{-- Lock the page behind the modal so the wheel scrolls the log, not the board. --}}
    x-data="{
        prevHtml: '',
        prevBody: '',
        init() {
            this.prevHtml = document.documentElement.style.overflow;
            this.prevBody = document.body.style.overflow;
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
        },
        destroy() {
            document.documentElement.style.overflow = this.prevHtml;
            document.body.style.overflow = this.prevBody;
        },
    }"
>
    <div class="flex w-full flex-col bg-white" style="max-width: 640px; max-height: calc(100vh - 48px); border-radius: 6px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); color: #1f2937;">
        {{-- Header: task dot + name --}}
        <div class="relative flex-none" style="padding: 14px 40px; border-bottom: 1px solid #e5e7eb;">
            <h2 class="flex items-center justify-center gap-2" style="font-size: 16px; font-weight: 700;">
                <span style="display: inline-block; width: 10px; height: 10px; border-radius: 9999px; flex: none; background-color: {{ $dot }};"></span>
                <span class="truncate">{{ $task->name }}</span>
                <span style="font-weight: 400; color: #6b7280;">— History</span>
            </h2>
            <button type="button" wire:click="closeTaskHistory" class="absolute hover:text-gray-700" style="top: 14px; right: 14px; color: #9ca3af;" title="Close">
                <x-heroicon-m-x-mark class="h-5 w-5" />
            </button>
        </div>

        {{-- Filters + total --}}
        <div class="flex flex-none flex-wrap items-center gap-2" style="padding: 10px 16px; border-bottom: 1px solid #e5e7eb;">
            <select
                wire:model.live="historyPeriod"
                data-testid="task-history-period"
                style="border: 1px solid #d1d5db; border-radius: 4px; padding: 5px 28px 5px 8px; font-size: 13px; color: #1f2937; background-color: #ffffff;"
            >
                <option value="today">Period: Today</option>
                <option value="this_week">Period: This week</option>
                <option value="this_last_week">Period: This + Last week</option>
                <option value="this_month">Period: This month</option>
                <option value="all">Period: All time</option>
            </select>
            <button
                type="button"
                wire:click="openHistoryAddTime"
                data-testid="task-history-add-item"
                class="flex items-center gap-1 hover:opacity-80"
                style="font-size: 13px; font-weight: 600; color: #2563eb;"
            >
                <x-heroicon-m-plus class="h-4 w-4" />
                Add item
            </button>
            <span style="margin-left: auto; font-size: 12px; font-weight: 600; color: #6b7280;">
                Total {{ Format::seconds($this->getTaskHistoryDays()->sum('seconds')) }}
            </span>
        </div>

        {{-- Day groups --}}
        <div class="min-h-0 flex-1 overflow-y-auto" style="padding: 12px 16px; background-color: #f3f4f6; overscroll-behavior: contain;">
            @forelse ($this->getTaskHistoryDays() as $day)
                <div class="bg-white" style="border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 12px;">
                    {{-- Day header: label + totals + per-day "Add item" --}}
                    <div class="flex items-baseline gap-4" style="padding: 10px 14px; border-bottom: 1px solid #e5e7eb;">
                        <span style="font-size: 16px; font-weight: 700;">{{ $day['label'] }}</span>
                        <span style="font-size: 12px; font-weight: 600; color: #6b7280;">{{ Format::seconds($day['seconds']) }}</span>
                        <span style="font-size: 12px; font-weight: 600; color: #6b7280;">{{ $day['pomodoros'] }} {{ \Illuminate\Support\Str::plural('Pomodoro', $day['pomodoros']) }}</span>
                        <button
                            type="button"
                            wire:click="openHistoryAddTime('{{ $day['date']->toDateString() }}')"
                            data-testid="task-history-day-add-item"
                            class="flex items-center gap-1 hover:opacity-80"
                            style="margin-left: auto; font-size: 12px; font-weight: 600; color: #2563eb;"
                        >
                            <x-heroicon-m-plus class="h-4 w-4" />
                            Add item
                        </button>
                    </div>

                    @foreach ($day['entries'] as $entry)
                        @php
                            $successful = $entry->type === 'pomodoro' && $entry->seconds >= $workSeconds;
                            $sublabel = $successful
                                ? 'Successful Pomodoro'
                                : ($entry->reason ?? ($entry->type === 'stopwatch' ? 'Stopwatch' : ucfirst($entry->type)));
                        @endphp
                        <div class="group flex items-start gap-2" style="padding: 9px 14px; border-top: 1px solid #f3f4f6;" wire:key="history-entry-{{ $entry->id }}">
                            <span style="display: inline-block; width: 10px; height: 10px; margin-top: 4px; border-radius: 9999px; flex: none; background-color: {{ $dot }};"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate" style="font-size: 13px; font-weight: 700;">{{ $task->name }}</p>
                                @if ($sublabel)
                                    <p style="font-size: 12px; font-style: italic; color: {{ $successful ? '#15803d' : '#6b7280' }};">{{ $sublabel }}</p>
                                @endif
                            </div>
                            <div class="flex flex-none items-center gap-3" style="padding-top: 1px;">
                                <span class="font-mono" style="font-size: 12px; color: #374151;">{{ $entry->started_at->format('g:i A') }} - {{ $entry->ended_at->format('g:i A') }}</span>
                                <span class="font-mono" style="font-size: 12px; color: #374151; min-width: 32px; text-align: right;">{{ Format::seconds($entry->seconds) }}</span>
                                <button
                                    type="button"
                                    wire:click="deleteHistoryEntry({{ $entry->id }})"
                                    wire:confirm="Delete this time entry?"
                                    class="opacity-0 hover:!text-red-600 group-hover:opacity-100"
                                    style="color: #9ca3af;"
                                    title="Delete entry"
                                >
                                    <x-heroicon-m-trash class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="bg-white text-center" style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 24px; font-size: 13px; color: #6b7280;">No time entries for this task in this period.</p>
            @endforelse
        </div>
    </div>
</div>
