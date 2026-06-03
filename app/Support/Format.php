<?php

namespace App\Support;

class Format
{
    /**
     * Human-friendly duration: "2h 28m", "25m", or "0m".
     * Used by board cards, the detail modal, and the Pomodoro popup so the
     * format stays identical everywhere.
     */
    public static function seconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        if ($h) {
            return "{$h}h {$m}m";
        }

        return $m ? "{$m}m" : '0m';
    }
}
