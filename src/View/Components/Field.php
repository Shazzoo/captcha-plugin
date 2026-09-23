<?php

namespace Shazzoo\Captcha\View\Components;

use Illuminate\View\Component;
use Shazzoo\Captcha\Support\Guard;
use Shazzoo\Captcha\Support\Honeypot;

class Field extends Component
{
    public function __construct(
        /** Welk formulier dit is; bepaalt de action op de widget. */
        public string $context = 'default',
    ) {}

    public function action(): ?string
    {
        return Guard::actionFor($this->context);
    }

    public function honeypotField(): string
    {
        return Honeypot::fieldName();
    }

    public function timeField(): string
    {
        return Honeypot::TIME_FIELD;
    }

    public function signedTimestamp(): string
    {
        return Honeypot::signedTimestamp();
    }

    public function sitekey(): ?string
    {
        return config('captcha.provider') === 'turnstile'
            ? config('captcha.providers.turnstile.sitekey')
            : null;
    }

    public function scriptUrl(): string
    {
        return (string) config('captcha.providers.turnstile.script_url');
    }

    public function render()
    {
        return view('captcha::field');
    }
}
