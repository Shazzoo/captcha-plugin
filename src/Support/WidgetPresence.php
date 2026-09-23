<?php

namespace Shazzoo\Captcha\Support;

/**
 * Onthoudt binnen één verzoek of er een bewaakt Livewire-component gerenderd
 * is. De middleware injecteert de widget alleen dan.
 *
 * Bewust geen HTML-inspectie: welke componenten er op de pagina staan weet
 * Livewire zelf al, en dat laten vertellen is minder breekbaar dan zoeken naar
 * markup die morgen anders is.
 */
class WidgetPresence
{
    private bool $needed = false;

    private ?string $selector = null;

    private string $position = 'append';

    private ?string $action = null;

    public function markNeeded(?string $selector = null, string $position = 'append', ?string $action = null): void
    {
        $this->needed = true;

        $this->action ??= $action;

        // Eerste bewaakte component op de pagina bepaalt de plek; een tweede
        // zou hem anders verslepen naar een formulier waar hij niet hoort.
        if ($selector !== null && $this->selector === null) {
            $this->selector = $selector;
            $this->position = $position;
        }
    }

    public function selector(): ?string
    {
        return $this->selector;
    }

    public function position(): string
    {
        return $this->position;
    }

    public function action(): ?string
    {
        return $this->action;
    }

    public function isNeeded(): bool
    {
        return $this->needed;
    }
}
