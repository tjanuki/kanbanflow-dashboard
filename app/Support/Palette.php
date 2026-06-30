<?php

namespace App\Support;

/**
 * The fixed KanbanFlow colour palette: maps a colour name (which doubles as the
 * task ⇄ project join key) to its background / accent-dot / text hex values.
 *
 * Single source of truth for the swatches rendered across the board, the task
 * detail colour selector, and the "Edit color" dialog.
 */
class Palette
{
    /** Colour name => ['bg', 'dot', 'text']. */
    public const COLORS = [
        'white' => ['bg' => '#e5e7eb', 'dot' => '#9ca3af', 'text' => '#1f2937'],
        'yellow' => ['bg' => '#fdeaa8', 'dot' => '#f59e0b', 'text' => '#1f2937'],
        'green' => ['bg' => '#bbe8c8', 'dot' => '#22c55e', 'text' => '#1f2937'],
        'blue' => ['bg' => '#bfe3fb', 'dot' => '#3b9fd6', 'text' => '#1f2937'],
        'purple' => ['bg' => '#ddd6fe', 'dot' => '#8b5cf6', 'text' => '#1f2937'],
        'red' => ['bg' => '#fbcaca', 'dot' => '#ef4444', 'text' => '#1f2937'],
        'orange' => ['bg' => '#fdd9b5', 'dot' => '#f97316', 'text' => '#1f2937'],
        'magenta' => ['bg' => '#f9cfe4', 'dot' => '#ec4899', 'text' => '#1f2937'],
        'cyan' => ['bg' => '#aeebf2', 'dot' => '#06b6d4', 'text' => '#1f2937'],
        'brown' => ['bg' => '#e0d4cd', 'dot' => '#8d6e63', 'text' => '#1f2937'],
    ];

    /** The grey fallback used for an unknown colour name. */
    public const FALLBACK = ['bg' => '#e5e7eb', 'dot' => '#9ca3af', 'text' => '#1f2937'];

    /** @return array<string, array{bg: string, dot: string, text: string}> */
    public static function all(): array
    {
        return self::COLORS;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::COLORS);
    }

    public static function has(string $key): bool
    {
        return isset(self::COLORS[$key]);
    }

    /** @return array{bg: string, dot: string, text: string} */
    public static function tint(string $key): array
    {
        return self::COLORS[$key] ?? self::FALLBACK;
    }
}
