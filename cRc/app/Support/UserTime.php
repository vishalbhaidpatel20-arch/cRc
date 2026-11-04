<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The server runs in UTC but people plan in their own clock. The browser sends its timezone (the "crc_tz" cookie, and a hidden
 * "tz" field on the event form); times are stored in UTC and converted here. An unknown or missing zone falls back to the app's.
 */
class UserTime
{
    public static function zone(?Request $request = null): string
    {
        $request ??= request();

        foreach ([$request->input('tz'), $request->cookie('crc_tz')] as $candidate) {
            if (is_string($candidate) && self::isKnown($candidate)) {
                return $candidate;
            }
        }

        return config('app.timezone');
    }

    /**
     * Browsers report names like "Asia/Calcutta" (the old alias of Asia/Kolkata), so aliases count as known too.
     * An unknown zone is never an error: the caller just falls back to the app's zone.
     */
    public static function isKnown(string $zone): bool
    {
        static $known = null;
        $known ??= array_flip(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC));

        return isset($known[$zone]);
    }

    /** "2026-11-04" + "14:30" typed in $zone, as a UTC instant. */
    public static function toUtc(string $date, string $time, string $zone): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.$time, $zone)->utc();
    }

    /** A stored UTC time, shown in the viewer's clock. */
    public static function local($utc, string $zone): CarbonImmutable
    {
        $instant = $utc instanceof \DateTimeInterface ? CarbonImmutable::instance($utc) : CarbonImmutable::parse($utc, 'UTC');

        return $instant->setTimezone($zone);
    }
}
