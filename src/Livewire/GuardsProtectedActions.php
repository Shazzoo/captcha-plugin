<?php

namespace Shazzoo\Captcha\Livewire;

use Illuminate\Http\Request;
use Shazzoo\Captcha\Support\Guard;
use Shazzoo\Captcha\Support\Honeypot;
use Shazzoo\Captcha\Support\WidgetPresence;

use function Livewire\on;

/**
 * Bewaakt Livewire-acties van componenten die hier niets vanaf weten.
 *
 * Staat de componentnaam in config('captcha.livewire'), dan wordt de actie
 * gecontroleerd en zo nodig afgebroken -- het bewaakte component draait dan
 * niet. Dat component hoeft geen trait, geen property en geen import: het weet
 * niet dat deze plugin bestaat, en blijft werken als zij verwijderd wordt.
 *
 * Bewust via de eventbus van Livewire en niet via Livewire::componentHook():
 * ComponentHookRegistry::boot() koppelt alleen de hooks die op dát moment al
 * geregistreerd zijn, en dat gebeurt in de boot() van Livewire zelf. Een
 * plugin die later boot komt daar nooit meer tussen -- de hook wordt dan wel
 * bewaard maar nooit aangeroepen.
 *
 * De gegevens komen uit de headers die captcha.js aan elk Livewire-verzoek
 * hangt. Via properties zou niet kunnen: die zouden op het bewaakte component
 * gedeclareerd moeten staan.
 */
class GuardsProtectedActions
{
    public const TOKEN_HEADER = 'X-Captcha-Token';

    public const TRAP_HEADER = 'X-Captcha-Trap';

    public const TIME_HEADER = 'X-Captcha-Time';

    public static function listen(): void
    {
        // Onthoudt dat een bewaakt component gerenderd is, zodat de middleware
        // weet of de widget op deze pagina hoort. Zo hoeft niemand de HTML te
        // doorzoeken om te bepalen waar geïnjecteerd moet worden.
        on('render', function ($component): void {
            $settings = self::settingsFor($component);

            if ($settings !== null) {
                app(WidgetPresence::class)->markNeeded(
                    $settings['widget_selector'] ?? null,
                    (string) ($settings['widget_position'] ?? 'append'),
                    Guard::actionFor((string) ($settings['context'] ?? 'default')),
                );
            }
        });

        on('call', function ($component, $method, $params, $context, $returnEarly): void {
            self::guard($component, $method, $returnEarly);
        });
    }

    public static function guard(object $component, string $method, callable $returnEarly): void
    {
        $settings = self::settingsFor($component);

        if ($settings === null || ! in_array($method, (array) ($settings['methods'] ?? []), true)) {
            return;
        }

        $request = request();
        $guard = app(Guard::class);

        $result = $guard->check([
            Honeypot::fieldName() => $request->header(self::TRAP_HEADER, ''),
            Honeypot::TIME_FIELD => $request->header(self::TIME_HEADER, ''),
            $guard->provider()->tokenField() => $request->header(self::TOKEN_HEADER),
        ], $request instanceof Request ? $request : Request::create('/'), (string) ($settings['context'] ?? 'default'));

        if ($result->passed) {
            return;
        }

        // Op het validatieveld dat het component toch al toont, zodat de
        // melding verschijnt zonder dat wij zijn weergave hoeven te kennen.
        $component->addError((string) ($settings['error_field'] ?? 'captcha'), $result->message());

        // Een verbruikt token is niet nog eens te gebruiken; vraag de browser
        // om een verse challenge voor de volgende poging.
        $component->dispatch('captcha-reset');

        $returnEarly();
    }

    /** @return array<string, mixed>|null */
    private static function settingsFor(object $component): ?array
    {
        if (! method_exists($component, 'getName')) {
            return null;
        }

        /*
         * Bewust de hele array ophalen en zelf indexeren: een componentnaam als
         * "profiler.scan" bevat een punt, en config() zou die als padscheiding
         * lezen en dus in een niet-bestaande sublaag zoeken.
         */
        $settings = ((array) config('captcha.livewire'))[$component->getName()] ?? null;

        return is_array($settings) ? $settings : null;
    }
}
