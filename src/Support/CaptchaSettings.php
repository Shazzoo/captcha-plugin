<?php

namespace Shazzoo\Captcha\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * De instellingen die een beheerder in het paneel kan wijzigen, als laag over
 * config/captcha.php.
 *
 * Ze staan in de system settings van content-studio-core en niet in een eigen
 * tabel: het zijn er een handvol en zo blijft de plugin zonder migraties.
 * Elke waarde is optioneel -- een leeg veld valt terug op de config en dus op
 * de .env.
 */
final class CaptchaSettings
{
    public const string KEY = 'captcha_settings';

    /**
     * Welke opgeslagen sleutel welke config-sleutel overschrijft.
     *
     * @var array<string, string>
     */
    private const array MAP = [
        'provider' => 'captcha.provider',
        'sitekey' => 'captcha.providers.turnstile.sitekey',
        'secret' => 'captcha.providers.turnstile.secret',
        'honeypot_enabled' => 'captcha.honeypot.enabled',
        'honeypot_field' => 'captcha.honeypot.field',
        'min_seconds' => 'captcha.honeypot.min_seconds',
        'max_minutes' => 'captcha.honeypot.max_minutes',
        'protect' => 'captcha.protect',
        'fail_open_default' => 'captcha.fail_open.default',
        'fail_open_scan' => 'captcha.fail_open.profiler-scan',
    ];

    /**
     * Sleutels waar leeg, nul of false een echt antwoord is -- "niets
     * beschermen", "geen ondergrens", "niet doorlaten" -- en die dus de filter
     * in save() moeten overleven.
     *
     * @var string[]
     */
    private const array KEEPS_EMPTY = [
        'protect',
        'min_seconds',
        'honeypot_enabled',
        'fail_open_default',
        'fail_open_scan',
    ];

    /**
     * Versleuteld opgeslagen. Het secret is een credential voor Cloudflare:
     * wie de database of het paneel kan lezen zou het anders zo kunnen
     * meenemen.
     *
     * @var string[]
     */
    private const array ENCRYPTED = ['secret'];

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $stored = function_exists('sys_get') ? sys_get(self::KEY, []) : [];

        if (! is_array($stored)) {
            return [];
        }

        foreach (self::ENCRYPTED as $key) {
            if (isset($stored[$key]) && is_string($stored[$key])) {
                $stored[$key] = self::decrypt($stored[$key]);
            }
        }

        return $stored;
    }

    /**
     * Verdraagt een waarde die van voor de versleuteling stamt: die blijft
     * bruikbaar en wordt bij het eerstvolgende opslaan versleuteld.
     */
    private static function decrypt(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function save(array $settings): void
    {
        $stored = array_filter(
            array_intersect_key($settings, self::MAP),
            fn (mixed $value, string $key): bool => in_array($key, self::KEEPS_EMPTY, true)
                ? $value !== null
                : $value !== null && $value !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        foreach (self::ENCRYPTED as $key) {
            if (isset($stored[$key]) && is_string($stored[$key]) && $stored[$key] !== '') {
                $stored[$key] = Crypt::encryptString($stored[$key]);
            }
        }

        sys_set(self::KEY, $stored);

        self::apply();
    }

    /**
     * Legt de opgeslagen instellingen over de config. Draait in boot(), voordat
     * de middleware aan de routes gehangen wordt, zodat een gewijzigde lijst
     * met beschermde routes meteen klopt.
     */
    public static function apply(): void
    {
        foreach (self::all() as $key => $value) {
            if (! isset(self::MAP[$key]) || $value === null) {
                continue;
            }

            if ($value === '' && ! in_array($key, self::KEEPS_EMPTY, true)) {
                continue;
            }

            config([self::MAP[$key] => $value]);
        }
    }
}
