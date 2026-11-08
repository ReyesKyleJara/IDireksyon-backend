<?php

use App\Support\ProcessingTime;

it('reads the slowest estimate from catalog text', function (?string $text, ?int $days) {
    expect(ProcessingTime::estimatedDays($text))->toBe($days);
})->with([
    ['Same day', 0],
    ['same-day release', 0],
    ['3 days', 3],
    ['1 calendar day', 1],
    ['1–3 calendar days', 3],
    ['10 working days', 10],
    ['10–12 working days', 12],
    ['1-10 working days', 10],
    ['2–4 weeks', 28],
    ['2 to 3 months', 90],
    ['24 hours', 0],
    ['Varies', null],
    ['', null],
    [null, null],
    ['Digital and paper-format availability and physical-card delivery vary in 3 days.', null],
]);
