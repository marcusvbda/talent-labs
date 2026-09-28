<?php

use App\Models\User;
use App\Outreach\Support\SendScheduler;
use Carbon\CarbonImmutable;

/*
 * SendScheduler window math without a database: anchor() (the only DB read)
 * is stubbed, the user is an unsaved model and time is frozen.
 */

const LOCAL_TZ = 'America/Sao_Paulo';

beforeEach(function (): void {
    config([
        'talent.outreach.interval_min_seconds' => 45,
        'talent.outreach.interval_max_seconds' => 120,
        'talent.outreach.window.enabled' => true,
        'talent.outreach.window.start' => '08:00',
        'talent.outreach.window.end' => '19:00',
        'talent.outreach.window.weekdays_only' => false,
    ]);

    $this->user = new User(['timezone' => LOCAL_TZ]);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function schedulerWithAnchor(?CarbonImmutable $anchor): SendScheduler
{
    $scheduler = Mockery::mock(SendScheduler::class)->makePartial();
    $scheduler->shouldReceive('anchor')->andReturn($anchor);

    return $scheduler;
}

function freezeLocal(string $localDateTime): CarbonImmutable
{
    $now = CarbonImmutable::parse($localDateTime, LOCAL_TZ)->setTimezone('UTC');
    CarbonImmutable::setTestNow($now);

    return $now;
}

it('spaces the next slot within [min, max] seconds after the anchor', function (): void {
    config(['talent.outreach.window.enabled' => false]);
    $now = freezeLocal('2026-09-30 10:00:00');
    $scheduler = schedulerWithAnchor($now);

    foreach (range(1, 200) as $ignored) {
        $gap = $now->diffInSeconds($scheduler->nextSlot($this->user), true);

        expect($gap)->toBeGreaterThanOrEqual(45)->toBeLessThanOrEqual(120);
    }
});

it('never schedules before now when the anchor is old', function (): void {
    config(['talent.outreach.window.enabled' => false]);
    $now = freezeLocal('2026-09-30 10:00:00');

    $slot = schedulerWithAnchor($now->subHour())->nextSlot($this->user);

    expect($slot->getTimestamp())->toBe($now->getTimestamp());
});

it('sends right away when there is no anchor and the window is open', function (): void {
    $now = freezeLocal('2026-09-30 10:00:00');

    $slot = schedulerWithAnchor(null)->nextSlot($this->user);

    expect($slot->getTimestamp())->toBe($now->getTimestamp());
});

it('chains consecutive slots in order, each spaced within [min, max]', function (): void {
    config(['talent.outreach.window.enabled' => false]);
    freezeLocal('2026-09-30 10:00:00');
    $scheduler = schedulerWithAnchor(null);

    $previous = null;

    foreach (range(1, 30) as $ignored) {
        $slot = $scheduler->nextSlot($this->user, $previous);

        if ($previous !== null) {
            $gap = $slot->getTimestamp() - $previous->getTimestamp();

            expect($gap)->toBeGreaterThanOrEqual(45)->toBeLessThanOrEqual(120);
        }

        $previous = $slot;
    }
});

it('moves a slot after the window end to the next window start plus jitter', function (): void {
    freezeLocal('2026-09-30 20:00:00'); // Wednesday, after 19:00

    $local = schedulerWithAnchor(null)->nextSlot($this->user)->setTimezone(LOCAL_TZ);

    expect($local->toDateString())->toBe('2026-10-01')
        ->and($local->format('H:i:s') >= '08:00:00')->toBeTrue()
        ->and($local->format('H:i:s') <= '08:02:00')->toBeTrue();
});

it('moves a slot before the window start to the same day window start', function (): void {
    freezeLocal('2026-09-30 06:30:00');

    $local = schedulerWithAnchor(null)->nextSlot($this->user)->setTimezone(LOCAL_TZ);

    expect($local->toDateString())->toBe('2026-09-30')
        ->and($local->format('H:i:s') >= '08:00:00')->toBeTrue()
        ->and($local->format('H:i:s') <= '08:02:00')->toBeTrue();
});

it('moves a spaced slot that crosses the window end to the next day', function (): void {
    $now = freezeLocal('2026-09-30 18:59:30');

    $local = schedulerWithAnchor($now)->nextSlot($this->user)->setTimezone(LOCAL_TZ);

    expect($local->toDateString())->toBe('2026-10-01')
        ->and($local->format('H:i:s') >= '08:00:00')->toBeTrue()
        ->and($local->format('H:i:s') <= '08:02:00')->toBeTrue();
});

it('skips the weekend when weekdays_only is on', function (string $localNow): void {
    config(['talent.outreach.window.weekdays_only' => true]);
    freezeLocal($localNow);

    $local = schedulerWithAnchor(null)->nextSlot($this->user)->setTimezone(LOCAL_TZ);

    expect($local->toDateString())->toBe('2026-10-05')
        ->and($local->isMonday())->toBeTrue()
        ->and($local->format('H:i:s') >= '08:00:00')->toBeTrue()
        ->and($local->format('H:i:s') <= '08:02:00')->toBeTrue();
})->with([
    'friday evening' => '2026-10-02 20:00:00',
    'saturday noon' => '2026-10-03 12:00:00',
    'sunday noon' => '2026-10-04 12:00:00',
]);

it('keeps the weekend when weekdays_only is off', function (): void {
    freezeLocal('2026-10-02 20:00:00'); // Friday

    $local = schedulerWithAnchor(null)->nextSlot($this->user)->setTimezone(LOCAL_TZ);

    expect($local->toDateString())->toBe('2026-10-03')
        ->and($local->isSaturday())->toBeTrue();
});

it('treats every moment as inside the window when the window is disabled', function (): void {
    config([
        'talent.outreach.window.enabled' => false,
        'talent.outreach.window.weekdays_only' => true,
    ]);
    $now = freezeLocal('2026-10-04 03:00:00'); // Sunday, outside hours

    $scheduler = schedulerWithAnchor(null);

    expect($scheduler->isInsideWindow($this->user))->toBeTrue()
        ->and($scheduler->nextSlot($this->user)->getTimestamp())->toBe($now->getTimestamp());
});

it('evaluates the window in the user timezone', function (): void {
    // 10:00 UTC is 07:00 in São Paulo: outside an 08:00–19:00 window.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-30 10:00:00', 'UTC'));

    $scheduler = schedulerWithAnchor(null);

    expect($scheduler->isInsideWindow($this->user))->toBeFalse()
        ->and($scheduler->isInsideWindow(new User(['timezone' => 'UTC'])))->toBeTrue();
});
