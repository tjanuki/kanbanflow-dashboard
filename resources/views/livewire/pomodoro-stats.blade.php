<div>
    {{-- The launcher lives in the top bar (see PomodoroStatsButton / USER_MENU_BEFORE);
         this component only renders the modal, at BODY_END so the fixed overlay is never clipped. --}}
    @if ($showStats)
        @php $chart = $this->chart; @endphp
        {{-- Keep hover dialogs hidden until Alpine initialises (no flash of all tips at once). --}}
        <style>[x-cloak]{display:none !important;}</style>
        <div
            data-testid="pomodoro-stats-modal"
            class="fixed inset-0 flex items-center justify-center"
            style="z-index: 100; background-color: rgba(0, 0, 0, 0.45); padding: 16px;"
            wire:click.self="close"
            x-on:keydown.escape.window="$wire.close()"
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
            <div
                class="flex w-full flex-col bg-white"
                style="max-width: 1180px; height: calc(100vh - 32px); border-radius: 6px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); color: #1f2937; overflow: hidden;"
            >
                {{-- Header: Export (left) · title (centre) · close (right) --}}
                <div class="relative flex flex-none items-center" style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                    <button
                        type="button"
                        wire:click="export"
                        data-testid="pomodoro-stats-export"
                        class="inline-flex items-center gap-1.5 hover:text-gray-900"
                        style="font-size: 14px; color: #4b5563;"
                        title="Export as CSV"
                    >
                        <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                        Export
                    </button>
                    <h2 style="position: absolute; left: 50%; transform: translateX(-50%); font-size: 16px; font-weight: 700;">Pomodoro Statistics</h2>
                    <button type="button" wire:click="close" class="hover:text-gray-700" style="margin-left: auto; color: #9ca3af;" title="Close">
                        <x-heroicon-m-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex min-h-0 flex-1 flex-col">
                    {{-- Filter row --}}
                    <div class="flex flex-none flex-wrap items-end justify-center gap-4" style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                        <div>
                            <label style="display: block; text-align: center; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Period</label>
                            <select wire:model.live="period" data-testid="pomodoro-stats-period" style="border: 1px solid #d1d5db; border-radius: 4px; padding: 6px 28px 6px 10px; font-size: 13px; color: #1f2937; background-color: #ffffff; min-width: 170px;">
                                <option value="last_7_days">Last 7 days</option>
                                <option value="last_30_days">Last 30 days</option>
                                <option value="last_90_days">Last 90 days</option>
                                <option value="this_month">This month</option>
                                <option value="this_year">This year</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; text-align: center; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px;">Group by</label>
                            <select wire:model.live="groupBy" data-testid="pomodoro-stats-groupby" style="border: 1px solid #d1d5db; border-radius: 4px; padding: 6px 28px 6px 10px; font-size: 13px; color: #1f2937; background-color: #ffffff;">
                                <option value="day">Day</option>
                                <option value="week">Week</option>
                                <option value="month">Month</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            wire:click="$refresh"
                            data-testid="pomodoro-stats-reload"
                            class="hover:opacity-90"
                            style="background-color: #4ac26b; color: #ffffff; border-radius: 4px; padding: 7px 16px; font-size: 13px; font-weight: 700;"
                        >
                            Reload
                        </button>
                    </div>

                    {{-- Chart: a flex column so the plot fills the space above the always-visible axis + total. --}}
                    <div class="min-h-0 flex-1" style="display: flex; flex-direction: column; padding: 24px 28px 16px;">
                        {{-- Plot area --}}
                        <div style="position: relative; flex: 1 1 0; min-height: 220px;">
                            {{-- Gridlines + y-axis labels --}}
                            @foreach ($chart['gridlines'] as $line)
                                <div style="position: absolute; left: 34px; right: 0; bottom: {{ $line['pct'] }}%; height: 1px; background-color: #ededed;"></div>
                                <div style="position: absolute; left: 0; bottom: {{ $line['pct'] }}%; transform: translateY(50%); font-size: 11px; color: #9ca3af;">{{ $line['value'] }}</div>
                            @endforeach

                            {{-- Bars --}}
                            <div style="position: absolute; left: 34px; right: 0; top: 0; bottom: 0; display: flex; align-items: flex-end; gap: 2px;">
                                @foreach ($chart['buckets'] as $bucket)
                                    <div
                                        x-data="{ show: false }"
                                        style="position: relative; flex: 1 1 0; min-width: 0; height: 100%; display: flex; align-items: flex-end; justify-content: center;"
                                    >
                                        {{-- Hover trigger is the bar itself, not the full-height column. --}}
                                        <div
                                            x-on:mouseenter="show = true"
                                            x-on:mouseleave="show = false"
                                            style="width: 60%; max-width: 26px; height: {{ $bucket['heightPct'] }}%; background-color: #69c34a; border-radius: 1px;"
                                        ></div>

                                        {{-- Hover dialog: dark card with the date range + green count. --}}
                                        <div
                                            x-show="show"
                                            x-cloak
                                            style="position: absolute; top: {{ min(88, max(10, 100 - $bucket['heightPct'])) }}%; transform: translateY(-50%); {{ $bucket['flip'] ? 'right: calc(50% + 14px);' : 'left: calc(50% + 14px);' }} z-index: 30; white-space: nowrap; pointer-events: none; background-color: #454545; color: #ffffff; border-radius: 6px; padding: 8px 12px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.28);"
                                        >
                                            <div style="font-size: 13px; line-height: 1.35;">
                                                <span style="font-weight: 700;">{{ $bucket['tipStart'] }}</span>@if ($bucket['tipEnd']) <span style="color: #cfcfcf;">to</span> <span style="font-weight: 700;">{{ $bucket['tipEnd'] }}</span>@endif
                                            </div>
                                            <div style="font-size: 13px; line-height: 1.35; color: #e2e2e2;">
                                                Pomodoros: <span style="color: #6cc04a; font-weight: 700;">{{ $bucket['count'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- X-axis labels (overflow their narrow column into blank neighbours so dates aren't clipped) --}}
                        <div class="flex-none" style="display: flex; gap: 2px; padding-left: 34px; margin-top: 8px;">
                            @foreach ($chart['buckets'] as $bucket)
                                <div style="flex: 1 1 0; min-width: 0; text-align: center; font-size: 11px; color: #9ca3af; white-space: nowrap; overflow: visible;">{{ $bucket['showLabel'] ? $bucket['label'] : '' }}</div>
                            @endforeach
                        </div>

                        <p class="flex-none text-center" style="margin-top: 14px; font-size: 12px; color: #9ca3af;">
                            {{ $chart['total'] }} {{ \Illuminate\Support\Str::plural('Pomodoro', $chart['total']) }} in this period
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
