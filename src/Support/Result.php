<?php

namespace Shazzoo\Captcha\Support;

class Result
{
    private function __construct(
        public readonly bool $passed,
        public readonly ?string $layer = null,
        public readonly ?string $messageKey = null,
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    /** @param  string  $layer  honeypot|timing|provider -- welke laag afwees, voor de logs */
    public static function fail(string $layer, string $messageKey = 'captcha::captcha.failed'): self
    {
        return new self(false, $layer, $messageKey);
    }

    public function message(): string
    {
        return __($this->messageKey ?? 'captcha::captcha.failed');
    }
}
