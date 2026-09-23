<?php

namespace Shazzoo\Captcha\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shazzoo\Captcha\Contracts\CaptchaProvider;
use Throwable;

/**
 * De enige plek waar de lagen op volgorde langskomen. Middleware en de
 * Livewire-trait doen allebei niets anders dan dit aanroepen, zodat een
 * contactformulier en een scan dezelfde regels krijgen.
 */
class Guard
{
    public function __construct(private readonly CaptchaProvider $provider) {}

    /**
     * @param  array<string, mixed>  $input  de ingezonden velden
     * @param  string  $context  sleutel in captcha.fail_open
     */
    public function check(array $input, Request $request, string $context = 'default'): Result
    {
        if ($reason = Honeypot::check($input)) {
            return $this->reject($reason === 'captcha::captcha.failed' ? 'honeypot' : 'timing', $reason, $request);
        }

        $token = $input[$this->provider->tokenField()] ?? null;

        try {
            $passed = $this->provider->verify(
                is_string($token) ? $token : null,
                $request,
                self::actionFor($context),
            );
        } catch (Throwable $e) {
            return $this->onProviderFailure($e, $request, $context);
        }

        return $passed
            ? Result::pass()
            : $this->reject('provider', 'captcha::captcha.failed', $request);
    }

    public function provider(): CaptchaProvider
    {
        return $this->provider;
    }

    /** De action die de widget van dit formulier meestuurt. */
    public static function actionFor(string $context): ?string
    {
        $action = ((array) config('captcha.actions'))[$context] ?? null;

        return is_string($action) && $action !== '' ? $action : null;
    }

    private function onProviderFailure(Throwable $e, Request $request, string $context): Result
    {
        $failOpen = (bool) (config('captcha.fail_open.'.$context) ?? config('captcha.fail_open.default', true));

        // Altijd loggen: een provider die stilletjes wegvalt terwijl wij
        // doorlaten, is precies het soort storing dat je pas maanden later ziet.
        Log::warning('Captcha provider unavailable', [
            'context' => $context,
            'fail_open' => $failOpen,
            'error' => $e->getMessage(),
            'ip' => $request->ip(),
        ]);

        return $failOpen ? Result::pass() : Result::fail('provider', 'captcha::captcha.failed');
    }

    private function reject(string $layer, string $messageKey, Request $request): Result
    {
        // Geen berichtinhoud in de logs, conform de keuze in de profiler-plugin.
        Log::info('Captcha rejected a submission', [
            'layer' => $layer,
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return Result::fail($layer, $messageKey);
    }
}
