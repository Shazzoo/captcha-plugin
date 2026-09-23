<?php

namespace Shazzoo\Captcha\Providers;

use Illuminate\Http\Request;
use Shazzoo\Captcha\Contracts\CaptchaProvider;

/**
 * De standaard: geen externe challenge. Honeypot en timing doen dan het werk,
 * zodat de plugin bruikbaar is zonder account of sleutels.
 */
class NullProvider implements CaptchaProvider
{
    public function verify(?string $token, Request $request, ?string $expectedAction = null): bool
    {
        return true;
    }

    public function tokenField(): string
    {
        return 'captcha_token';
    }
}
