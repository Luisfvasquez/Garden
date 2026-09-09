<?php

declare(strict_types=1);

use App\Enums\LeapDayPolicy;
use App\Enums\RecurrenceType;
use App\Models\LetterSchedule;
use App\Services\Scheduling\OccurrenceGenerator;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->gen = new OccurrenceGenerator);

function makeSchedule(array $attrs): LetterSchedule
{
    return LetterSchedule::factory()->make($attrs);
}

it('yields a single occurrence for "once"', function (): void {
    $s = makeSchedule([
        'recurrence_type' => RecurrenceType::Once,
        'anchor_date' => '2027-04-12',
        'local_time' => '09:00',
        'timezone' => 'Europe/Madrid',
        'occurrences_total' => 1,
    ]);

    $occ = $this->gen->all($s);

    expect($occ)->toHaveCount(1)
        ->and($occ[0]->date)->toBe('2027-04-12')
        ->and($occ[0]->runsAt->toIso8601ZuluString())->toBe('2027-04-12T07:00:00Z'); // CEST = UTC+2
});

it('steps yearly off the anchor', function (): void {
    $s = makeSchedule([
        'recurrence_type' => RecurrenceType::Yearly,
        'anchor_date' => '2027-04-12',
        'occurrences_total' => 3,
    ]);

    expect(array_map(fn ($o) => $o->date, $this->gen->all($s)))
        ->toBe(['2027-04-12', '2028-04-12', '2029-04-12']);
});

it('steps weekly and monthly', function (): void {
    $weekly = makeSchedule([
        'recurrence_type' => RecurrenceType::Weekly,
        'anchor_date' => '2027-01-01',
        'occurrences_total' => 3,
    ]);
    $monthly = makeSchedule([
        'recurrence_type' => RecurrenceType::Monthly,
        'anchor_date' => '2027-01-31',
        'occurrences_total' => 3,
    ]);

    expect(array_map(fn ($o) => $o->date, $this->gen->all($weekly)))
        ->toBe(['2027-01-01', '2027-01-08', '2027-01-15']);

    // Jan 31 → clamp to end of Feb → Mar 31
    expect(array_map(fn ($o) => $o->date, $this->gen->all($monthly)))
        ->toBe(['2027-01-31', '2027-02-28', '2027-03-31']);
});

it('applies leap_day_policy feb_28 for a 29-Feb anchor in common years', function (): void {
    $s = makeSchedule([
        'recurrence_type' => RecurrenceType::Yearly,
        'anchor_date' => '2028-02-29',
        'leap_day_policy' => LeapDayPolicy::Feb28,
        'occurrences_total' => 3,
    ]);

    expect(array_map(fn ($o) => $o->date, $this->gen->all($s)))
        ->toBe(['2028-02-29', '2029-02-28', '2030-02-28']);
});

it('applies leap_day_policy mar_01 for a 29-Feb anchor in common years', function (): void {
    $s = makeSchedule([
        'recurrence_type' => RecurrenceType::Yearly,
        'anchor_date' => '2028-02-29',
        'leap_day_policy' => LeapDayPolicy::Mar01,
        'occurrences_total' => 3,
    ]);

    expect(array_map(fn ($o) => $o->date, $this->gen->all($s)))
        ->toBe(['2028-02-29', '2029-03-01', '2030-03-01']);
});

it('freezes the local wall-clock time across a DST boundary', function (): void {
    // Madrid: CET (UTC+1) in winter, CEST (UTC+2) in summer. 09:00 local stays
    // 09:00 local — the UTC instant shifts, not the other way around.
    $s = makeSchedule([
        'recurrence_type' => RecurrenceType::CustomDates,
        'custom_dates' => ['2027-01-15', '2027-07-15'],
        'local_time' => '09:00',
        'timezone' => 'Europe/Madrid',
        'occurrences_total' => null,
    ]);

    $occ = $this->gen->all($s);

    expect($occ[0]->runsAt->toIso8601ZuluString())->toBe('2027-01-15T08:00:00Z')
        ->and($occ[1]->runsAt->toIso8601ZuluString())->toBe('2027-07-15T07:00:00Z');
});

it('sorts and de-dupes custom_dates', function (): void {
    $s = makeSchedule([
        'recurrence_type' => RecurrenceType::CustomDates,
        'custom_dates' => ['2027-05-01', '2027-01-01', '2027-05-01'],
        'occurrences_total' => null,
    ]);

    expect(array_map(fn ($o) => $o->date, $this->gen->all($s)))
        ->toBe(['2027-01-01', '2027-05-01']);
});

it('filters to a horizon window with upcoming()', function (): void {
    $s = makeSchedule([
        'recurrence_type' => RecurrenceType::Yearly,
        'anchor_date' => '2027-04-12',
        'occurrences_total' => 5,
    ]);

    $from = CarbonImmutable::parse('2027-01-01T00:00:00Z');

    // 90 days from 2027-01-01 reaches early April — only the 2027 occurrence.
    expect($this->gen->upcoming($s, $from, 120))->toHaveCount(1)
        ->and($this->gen->upcoming($s, $from, 30))->toHaveCount(0);
});
