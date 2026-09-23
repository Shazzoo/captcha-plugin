<?php

namespace Shazzoo\Captcha\Contracts;

use Illuminate\Http\Request;

interface CaptchaProvider
{
    /**
     * Mag deze inzending door?
     *
     * Gooit een exception als de provider zelf onbereikbaar is -- dat is iets
     * anders dan een afgewezen bezoeker, en de Guard beslist per formulier wat
     * er dan gebeurt (zie config captcha.fail_open).
     */
    public function verify(?string $token, Request $request, ?string $expectedAction = null): bool;

    /** Naam van het veld waarin de client het token meestuurt. */
    public function tokenField(): string;
}
