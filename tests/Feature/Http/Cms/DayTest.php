<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Http\Cms\Day;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-23 14:00:00'));
});

it('says today and yesterday in words', function (): void {
    expect(Day::of(CarbonImmutable::parse('2026-11-23 08:12:00'), 'nl'))->toBe('vandaag')
        ->and(Day::of(CarbonImmutable::parse('2026-11-22 23:59:00'), 'nl'))->toBe('gisteren')
        ->and(Day::of(CarbonImmutable::parse('2026-11-23 08:12:00'), 'en'))->toBe('today');
});

it('gives a day of this year without the year and without a dot after the month', function (): void {
    expect(Day::of(CarbonImmutable::parse('2026-10-20 10:00:00'), 'nl'))->toBe('20 okt')
        ->and(Day::of(CarbonImmutable::parse('2026-05-02 10:00:00'), 'nl'))->toBe('2 mei')
        ->and(Day::of(CarbonImmutable::parse('2026-10-20 10:00:00'), 'en'))->toBe('20 Oct');
});

it('gives a day of another year with the year', function (): void {
    expect(Day::of(CarbonImmutable::parse('2024-03-12 10:00:00'), 'nl'))->toBe('12 mrt 2024');
});

it('says a day in full, with the year only for another year', function (): void {
    expect(Day::long(CarbonImmutable::parse('2026-11-23 08:12:00'), 'nl'))->toBe('vandaag')
        ->and(Day::long(CarbonImmutable::parse('2026-11-22 23:59:00'), 'nl'))->toBe('gisteren')
        ->and(Day::long(CarbonImmutable::parse('2026-10-20 10:00:00'), 'nl'))->toBe('20 oktober')
        ->and(Day::long(CarbonImmutable::parse('2024-03-12 10:00:00'), 'nl'))->toBe('12 maart 2024')
        ->and(Day::long(CarbonImmutable::parse('2026-10-20 10:00:00'), 'en'))->toBe('20 October');
});

it('says a moment as its day in full with the time', function (): void {
    expect(Day::moment(CarbonImmutable::parse('2026-11-23 08:12:00'), 'nl'))->toBe('vandaag, 08:12')
        ->and(Day::moment(CarbonImmutable::parse('2026-10-20 14:12:00'), 'nl'))->toBe('20 oktober, 14:12')
        ->and(Day::moment(CarbonImmutable::parse('2024-03-12 10:00:00'), 'nl'))->toBe('12 maart 2024, 10:00')
        ->and(Day::moment(CarbonImmutable::parse('2026-10-20 14:12:00'), 'en'))->toBe('20 October, 2:12 PM');
});

it('says tomorrow in words', function (): void {
    expect(Day::of(CarbonImmutable::parse('2026-11-24 09:00:00'), 'nl'))->toBe('morgen')
        ->and(Day::long(CarbonImmutable::parse('2026-11-24 09:00:00'), 'nl'))->toBe('morgen')
        ->and(Day::of(CarbonImmutable::parse('2026-11-24 09:00:00'), 'en'))->toBe('tomorrow');
});

it('says at which moment something happens, in a sentence', function (): void {
    expect(Day::at(CarbonImmutable::parse('2026-11-23 16:00:00'), 'nl'))->toBe('vandaag om 16:00')
        ->and(Day::at(CarbonImmutable::parse('2026-11-24 09:00:00'), 'nl'))->toBe('morgen om 09:00')
        ->and(Day::at(CarbonImmutable::parse('2026-11-22 09:00:00'), 'nl'))->toBe('gisteren om 09:00')
        ->and(Day::at(CarbonImmutable::parse('2026-12-14 09:00:00'), 'nl'))->toBe('op 14 december om 09:00')
        ->and(Day::at(CarbonImmutable::parse('2027-01-04 09:00:00'), 'nl'))->toBe('op 4 januari 2027 om 09:00')
        ->and(Day::at(CarbonImmutable::parse('2026-12-14 09:00:00'), 'en'))->toBe('on 14 December at 9:00 AM')
        ->and(Day::at(CarbonImmutable::parse('2026-11-24 09:00:00'), 'en'))->toBe('tomorrow at 9:00 AM');
});

it('says how long ago a day was, in whole days of the calendar', function (string $day, string $nl, string $en): void {
    expect(Day::ago(CarbonImmutable::parse($day), 'nl'))->toBe($nl)
        ->and(Day::ago(CarbonImmutable::parse($day), 'en'))->toBe($en);
})->with([
    'today' => ['2026-11-23', 'vandaag', 'today'],
    'yesterday, late' => ['2026-11-22 23:59:00', 'gisteren', 'yesterday'],
    'two days' => ['2026-11-21', '2 dagen geleden', '2 days ago'],
    'months' => ['2026-08-25', '90 dagen geleden', '90 days ago'],
]);
