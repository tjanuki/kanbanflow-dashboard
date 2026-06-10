<?php

namespace App\Livewire;

use App\Models\TimeEntry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Small red countdown pill rendered into the Filament top bar. It mirrors the
 * running entry held by {@see PomodoroTimer} and toggles that popup on click.
 */
class PomodoroPill extends Component
{
    public int $workSeconds = 1500;

    /** Mirrors the running session's kind so the pill counts the right way. */
    public string $mode = 'pomodoro';

    public ?int $runningEntryId = null;

    public ?string $runningStartedAt = null;

    public function mount(): void
    {
        $this->loadRunningEntry();
    }

    /** Refresh whenever a timer starts or stops anywhere on the page. */
    #[On('pomodoro-updated')]
    public function loadRunningEntry(): void
    {
        $entry = TimeEntry::running()->latest('started_at')->first();

        if (! $entry) {
            $this->reset(['runningEntryId', 'runningStartedAt']);

            return;
        }

        $this->runningEntryId = $entry->id;
        $this->runningStartedAt = $entry->started_at->toIso8601String();

        if (in_array($entry->type, ['pomodoro', 'stopwatch'], true)) {
            $this->mode = $entry->type;
        }
    }

    /** Click → toggle the floating popup managed by PomodoroTimer. */
    public function toggle(): void
    {
        $this->dispatch('toggle-pomodoro');
    }

    public function render(): View
    {
        return view('livewire.pomodoro-pill');
    }
}
