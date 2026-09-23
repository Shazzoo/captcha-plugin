<?php

namespace Shazzoo\Captcha\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shazzoo\Captcha\Support\Honeypot;
use Shazzoo\Captcha\Support\WidgetPresence;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zet de widget op elke pagina waar een bewaakt Livewire-component staat.
 *
 * Dit is de prijs van de ontkoppeling: het bewaakte component plaatst zelf
 * geen <x-captcha::field>, dus moet de plugin de widget er zelf bij zetten.
 * Bewust alleen een toevoeging vlak voor </body> en niet ergens binnen het
 * formulier: er wordt niets van de opbouw van die pagina verondersteld, alleen
 * dat er een </body> is. Het token reist via een header en niet als
 * formulierveld, juist zodat we niet in het formulier hoeven te knippen.
 */
class InjectCaptchaWidget
{
    public function __construct(private readonly WidgetPresence $presence) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->presence->isNeeded()) {
            return $response;
        }

        // Alleen hele HTML-pagina's: een Livewire-update is JSON en krijgt de
        // widget dus niet nog een keer.
        if ($request->hasHeader('X-Livewire')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || ! str_contains($content, '</body>')) {
            return $response;
        }

        $markup = view('captcha::widget', [
            'sitekey' => config('captcha.provider') === 'turnstile'
                ? config('captcha.providers.turnstile.sitekey')
                : null,
            'scriptUrl' => config('captcha.providers.turnstile.script_url'),
            'trapField' => Honeypot::fieldName(),
            'timestamp' => Honeypot::signedTimestamp(),
            'mountSelector' => $this->presence->selector(),
            'mountPosition' => $this->presence->position(),
            'action' => $this->presence->action(),
        ])->render();

        $response->setContent(
            substr_replace($content, $markup.'</body>', strrpos($content, '</body>'), strlen('</body>'))
        );

        return $response;
    }
}
