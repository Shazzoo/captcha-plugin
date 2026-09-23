/**
 * Rendert de Turnstile-widgets en hangt de captcha-gegevens aan Livewire.
 *
 * Bewust een los bestand en geen inline script: zo blijft een strikte
 * Content-Security-Policy mogelijk zonder 'unsafe-inline' of een nonce per
 * request. Alle configuratie komt uit data-attributen, niet uit door Blade
 * ingespoten JS.
 *
 * Wordt aangeroepen door api.js via ?onload=captchaRenderWidgets. Beide
 * scripts staan op defer en draaien dus in documentvolgorde, waardoor deze
 * functie bestaat voordat Turnstile hem zoekt.
 */
window.captchaRenderWidgets = function () {
    document.querySelectorAll('[data-captcha-target]').forEach(function (el) {
        if (el.dataset.captchaRendered) {
            return;
        }

        var hidden = el.closest('[data-captcha-turnstile]')
            ? el.closest('[data-captcha-turnstile]').querySelector('[data-captcha-token]')
            : null;

        var options = {
            sitekey: el.dataset.sitekey,
            callback: function (token) {
                // Gewoon formulier: als verborgen veld mee in de POST.
                if (hidden) {
                    hidden.value = token;
                }

                // Livewire: opslaan voor de headers hieronder. Een property zou
                // het bewaakte component moeten declareren, en dat is precies
                // wat deze plugin niet wil vragen.
                var wrapper = el.closest('[data-captcha-livewire]');

                if (wrapper) {
                    wrapper.dataset.token = token;
                }
            },
        };

        // Siteverify krijgt deze action terug en de server vergelijkt hem, zodat
        // een token van het ene formulier niet op het andere werkt.
        if (el.dataset.action) {
            options.action = el.dataset.action;
        }

        el.dataset.captchaWidgetId = window.turnstile.render(el, options);

        el.dataset.captchaRendered = '1';
    });
};

/**
 * Hangt token, honeypot en ondertekende tijd aan elk Livewire-verzoek.
 *
 * Via headers en niet via properties: het bewaakte component weet niet dat
 * deze plugin bestaat en declareert dus niets. Een bot die het endpoint van
 * Livewire rechtstreeks aanroept draait dit script niet, stuurt dus geen
 * headers, en valt daarmee vanzelf door de controle.
 */
/**
 * Verplaatst de widget naar de plek uit config('captcha.livewire.<naam>').
 *
 * Nodig omdat het bewaakte component de widget niet zelf plaatst: hij wordt
 * vlak voor </body> geinjecteerd en staat daardoor standaard onder de footer.
 * Matcht de selector niets, dan blijft hij staan waar hij staat -- zichtbaar
 * op een rare plek is nog altijd beter dan een challenge die niemand kan
 * oplossen.
 */
function captchaMoveWidget() {
    var wrapper = document.querySelector('[data-captcha-livewire]');

    if (! wrapper || ! wrapper.dataset.mountSelector || wrapper.dataset.captchaMoved) {
        return;
    }

    var target;

    try {
        target = document.querySelector(wrapper.dataset.mountSelector);
    } catch (e) {
        return; // onbruikbare selector: laat hem staan
    }

    if (! target) {
        return;
    }

    switch (wrapper.dataset.mountPosition) {
        case 'prepend':
            target.prepend(wrapper);
            break;
        case 'before':
            target.before(wrapper);
            break;
        case 'after':
            target.after(wrapper);
            break;
        default:
            target.append(wrapper);
    }

    wrapper.dataset.captchaMoved = '1';
}

document.addEventListener('DOMContentLoaded', captchaMoveWidget);

document.addEventListener('livewire:init', function () {
    // Na een Livewire-update kan het doelelement opnieuw opgebouwd zijn.
    window.Livewire.hook('morphed', captchaMoveWidget);

    window.Livewire.hook('request', function (payload) {
        var wrapper = document.querySelector('[data-captcha-livewire]');

        if (! wrapper || ! payload.options || ! payload.options.headers) {
            return;
        }

        var trap = wrapper.querySelector('[data-captcha-trap]');

        payload.options.headers['X-Captcha-Token'] = wrapper.dataset.token || '';
        payload.options.headers['X-Captcha-Trap'] = trap ? trap.value : '';
        payload.options.headers['X-Captcha-Time'] = wrapper.dataset.timestamp || '';
    });

    // Na een afwijzing is het token verbruikt; de server vraagt via dit event
    // om een verse challenge.
    window.Livewire.on('captcha-reset', function () {
        var wrapper = document.querySelector('[data-captcha-livewire]');

        if (wrapper) {
            delete wrapper.dataset.token;
        }

        document.querySelectorAll('[data-captcha-target]').forEach(function (el) {
            if (el.dataset.captchaWidgetId && window.turnstile) {
                window.turnstile.reset(el.dataset.captchaWidgetId);
            }
        });
    });
});
