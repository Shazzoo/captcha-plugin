<?php

namespace Shazzoo\Captcha;

class Plugin
{
    public static function key(): string
    {
        return 'shazzoo/captcha';
    }

    public static function provider(): string
    {
        return CaptchaServiceProvider::class;
    }
}
