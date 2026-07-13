<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Top-bar chart icon that opens the Pomodoro Statistics modal. Mirrors the
 * pill → timer split: the button lives in the header while the modal it
 * controls ({@see PomodoroStats}) is rendered at the page body.
 */
class PomodoroStatsButton extends Component
{
    /** Click → open the statistics modal managed by PomodoroStats. */
    public function open(): void
    {
        $this->dispatch('open-pomodoro-stats');
    }

    public function render(): View
    {
        return view('livewire.pomodoro-stats-button');
    }
}
