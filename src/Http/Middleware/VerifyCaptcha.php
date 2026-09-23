<?php

namespace Shazzoo\Captcha\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shazzoo\Captcha\Support\Guard;
use Shazzoo\Captcha\Support\Honeypot;
use Symfony\Component\HttpFoundation\Response;

class VerifyCaptcha
{
    public function __construct(private readonly Guard $guard) {}

    public function handle(Request $request, Closure $next, ?string $context = null): Response
    {
        $context ??= $this->contextForRoute($request);

        // Niet beschermd: deze middleware draait op de hele web-group en laat
        // alles door wat niet in captcha.protect staat.
        if ($context === null) {
            return $next($request);
        }

        $result = $this->guard->check($request->all(), $request, $context);

        if ($result->passed) {
            return $next($request);
        }

        // Terug naar het formulier met de oude invoer, zodat een afgewezen mens
        // niet opnieuw hoeft te typen.
        return back()
            ->withInput($request->except([
                Honeypot::fieldName(),
                Honeypot::TIME_FIELD,
            ]))
            ->withErrors(['captcha' => $result->message()]);
    }

    /**
     * Null wanneer de route niet beschermd is.
     *
     * Bewust op naam bij elk verzoek in plaats van de middleware bij het booten
     * aan de route te hangen: met gecachete routes (php artisan route:cache)
     * bouwt CompiledRouteCollection::getRoutes() bij elke aanroep nieuwe Route-
     * objecten uit de cache, dus zo'n wijziging beland op een weggegooide kopie
     * en verdwijnt zonder foutmelding. Lokaal werkte het daardoor wel en op
     * productie niet.
     */
    private function contextForRoute(Request $request): ?string
    {
        $name = $request->route()?->getName();

        if ($name === null) {
            return null;
        }

        return in_array($name, (array) config('captcha.protect', []), true)
            ? 'default'
            : null;
    }
}
