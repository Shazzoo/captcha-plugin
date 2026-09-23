<?php

namespace Shazzoo\Captcha;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shazzoo\Captcha\Contracts\CaptchaProvider;
use Shazzoo\Captcha\Http\Middleware\InjectCaptchaWidget;
use Shazzoo\Captcha\Http\Middleware\VerifyCaptcha;
use Shazzoo\Captcha\Livewire\GuardsProtectedActions;
use Shazzoo\Captcha\Providers\NullProvider;
use Shazzoo\Captcha\Providers\TurnstileProvider;
use Shazzoo\Captcha\Support\CaptchaSettings;
use Shazzoo\Captcha\Support\Guard;
use Shazzoo\Captcha\Support\WidgetPresence;

/**
 * Self-contained zodat deze map zo naar een Composer-package te tillen is: hij
 * registreert zijn eigen views, vertalingen, asset en middleware en raakt
 * niets buiten de eigen namespace aan.
 */
class CaptchaServiceProvider extends ServiceProvider
{
    /** De host die zowel api.js als de iframe van de challenge serveert. */
    private const CHALLENGE_HOST = 'https://challenges.cloudflare.com';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/captcha.php', 'captcha');

        // De enige plek waar een vendor bij naam genoemd wordt; de rest van de
        // plugin kent alleen het contract.
        $this->app->singleton(CaptchaProvider::class, function ($app): CaptchaProvider {
            return match ($app['config']->get('captcha.provider')) {
                'turnstile' => new TurnstileProvider($app['config']->get('captcha.providers.turnstile', [])),
                default => new NullProvider,
            };
        });

        $this->app->singleton(Guard::class, fn ($app) => new Guard($app->make(CaptchaProvider::class)));

        // Per verzoek: onthoudt of er een bewaakt component gerenderd is.
        $this->app->scoped(WidgetPresence::class);
    }

    public function boot(): void
    {
        $base = dirname(__DIR__);

        // Vóór de middleware, zodat een in het paneel gewijzigde routelijst
        // meteen bepaalt wat er beschermd wordt.
        CaptchaSettings::apply();

        $this->loadViewsFrom($base.'/resources/views', 'captcha');
        $this->loadTranslationsFrom($base.'/lang', 'captcha');

        $this->publishes([
            $base.'/config/captcha.php' => config_path('captcha.php'),
        ], 'captcha-config');

        $this->publishes([
            $base.'/resources/views' => resource_path('views/vendor/captcha'),
        ], 'captcha-views');

        // app/ is niet web-bereikbaar, dus moet het script naar public/.
        // Hoort bij deploy, naast migrate.
        $this->publishes([
            $base.'/resources/js' => public_path('vendor/captcha'),
        ], 'captcha-assets');

        // PluginLoader leidt het component-alias af van de mapnaam; in vendor/
        // zou dat "captcha-plugin" worden. Zelf registreren houdt de slug uit
        // plugin.json leidend, waar de plugin ook staat.
        Blade::componentNamespace('Shazzoo\\Captcha\\View\\Components', 'captcha');

        $router = $this->app->make('router');

        $router->aliasMiddleware('captcha', VerifyCaptcha::class);

        /*
         * De twee helften van de Livewire-bescherming. Beide hangen aan de
         * namen in config('captcha.livewire'); het bewaakte component doet
         * zelf niets en hoeft niets te weten.
         */
        GuardsProtectedActions::listen();

        $router->pushMiddlewareToGroup('web', InjectCaptchaWidget::class);

        $this->allowChallengeHostInCsp();

        // Op de hele group; de middleware kijkt zelf of de route beschermd is.
        $router->pushMiddlewareToGroup('web', VerifyCaptcha::class);
    }

    /**
     * Zet challenges.cloudflare.com in de CSP van core.
     *
     * Zonder frame-src wordt de iframe van de challenge geblokkeerd en faalt
     * Turnstile met 300030: de widget verschijnt, maar er komt nooit een token
     * -- en omdat een leeg token als afwijzing telt, wordt daarmee iedereen
     * geweigerd. De plugin regelt dat dus zelf, zodat aanzetten volstaat en
     * uitzetten het ook weer opruimt.
     *
     * script-src is in veel opstellingen al gedekt door *.cloudflare.com, maar
     * wordt hier expliciet gezet: die wildcard is niet gegarandeerd.
     */
    private function allowChallengeHostInCsp(): void
    {
        if (config('captcha.provider') !== 'turnstile') {
            return;
        }

        foreach (['script_src', 'frame_src'] as $directive) {
            $key = 'cms.security.csp.'.$directive;
            $sources = (array) config($key, []);

            if (! in_array(self::CHALLENGE_HOST, $sources, true)) {
                $sources[] = self::CHALLENGE_HOST;
                config([$key => $sources]);
            }
        }
    }
}
