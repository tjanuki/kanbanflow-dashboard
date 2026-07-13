<div class="flex items-center">
    {{-- Statistics launcher: chart icon between the timer pill and the account menu. --}}
    <button
        type="button"
        wire:click="open"
        data-testid="pomodoro-stats-button"
        class="mr-2 inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
        title="Pomodoro statistics"
    >
        <x-heroicon-o-chart-bar class="h-6 w-6" />
    </button>
</div>
