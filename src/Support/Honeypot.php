<?php

namespace Shazzoo\Captcha\Support;

use Illuminate\Support\Facades\Config;

/**
 * Laag 1: een veld dat geen mens invult, plus hoe lang het formulier open
 * stond. De tijd wordt ondertekend meegestuurd in plaats van server-side
 * bewaard, zodat er niets op te slaan valt en er dus ook niets te lekken is.
 */
class Honeypot
{
    public const TIME_FIELD = 'captcha_ts';

    public static function fieldName(): string
    {
        return (string) Config::get('captcha.honeypot.field', 'website_url');
    }

    /** "<timestamp>|<signature>" -- waarde van het verborgen tijdveld. */
    public static function signedTimestamp(?int $at = null): string
    {
        $at ??= time();

        return $at.'|'.self::signature($at);
    }

    /** Null als alles klopt, anders de vertaalsleutel van de afwijzing. */
    public static function check(array $input): ?string
    {
        if (! Config::get('captcha.honeypot.enabled', true)) {
            return null;
        }

        if (filled($input[self::fieldName()] ?? null)) {
            return 'captcha::captcha.failed';
        }

        $raw = (string) ($input[self::TIME_FIELD] ?? '');

        // Geen tijdveld: het formulier draagt de field-component niet. Dat is
        // een fout van ons, niet van de bezoeker -- niet afwijzen.
        if ($raw === '') {
            return null;
        }

        [$at, $signature] = array_pad(explode('|', $raw, 2), 2, '');

        if (! ctype_digit($at) || ! hash_equals(self::signature((int) $at), (string) $signature)) {
            return 'captcha::captcha.failed';
        }

        $elapsed = time() - (int) $at;

        if ($elapsed < (int) Config::get('captcha.honeypot.min_seconds', 2)) {
            return 'captcha::captcha.too_fast';
        }

        if ($elapsed > (int) Config::get('captcha.honeypot.max_minutes', 60) * 60) {
            return 'captcha::captcha.expired';
        }

        return null;
    }

    private static function signature(int $at): string
    {
        return hash_hmac('sha256', 'captcha|'.$at, (string) Config::get('app.key'));
    }
}
