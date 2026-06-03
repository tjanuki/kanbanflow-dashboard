<?php

use App\Support\Format;

it('formats seconds into a human-friendly duration', function () {
    expect(Format::seconds(0))->toBe('0m')
        ->and(Format::seconds(25 * 60))->toBe('25m')
        ->and(Format::seconds(2 * 3600 + 28 * 60))->toBe('2h 28m')
        ->and(Format::seconds(3600))->toBe('1h 0m')
        ->and(Format::seconds(-50))->toBe('0m');
});
