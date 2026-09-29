<?php

return [
    // null laat alles door: de plugin is inert tot een provider gekozen is.
    'provider' => env('CAPTCHA_PROVIDER', 'null'),

    'honeypot' => [
        'enabled' => true,
        'field' => env('CAPTCHA_HONEYPOT_FIELD', 'website_url'),
        'min_seconds' => 2,   // sneller dan dit typt geen mens
        'max_minutes' => 60,  // ouder dan dit is een vergeten of herspeeld formulier
    ],

    // Routenamen die de captcha-middleware bewaakt. Voor gewone POST-formulieren.
    'protect' => ['contact-form.submit'],

    /*
     * Livewire-componenten die bewaakt worden, op de naam waarmee ze
     * geregistreerd zijn. Middleware helpt daar niet: die acties lopen over
     * het endpoint van Livewire en niet over de route van het formulier.
     *
     * Dit is bewust de enige plek waar staat wat er beschermd wordt. Het
     * bewaakte component weet van niets en hoeft niets te importeren; de
     * captcha-plugin haakt zichzelf in op de naam hieronder. Zo blijft een
     * plugin werken als deze plugin er niet is.
     *
     * Standaard leeg: een site die een component wil bewaken, zet dit in
     * zijn eigen config/captcha.php. Per component, op de naam waarmee het
     * geregistreerd is:
     *
     *     'naam.van.component' => [
     *         'methods' => ['submit'],     // de acties die bewaakt worden
     *         'error_field' => 'email',    // het validatieveld voor de melding,
     *                                      // zodat die verschijnt in de
     *                                      // foutweergave die het component al heeft
     *         'context' => 'mijn-formulier', // sleutel in actions hieronder
     *
     *         // Waar de widget terechtkomt: een CSS-selector op de pagina. Het
     *         // component plaatst hem niet zelf, dus zonder dit hangt hij
     *         // onderaan de pagina. Matcht de selector niets, dan blijft hij
     *         // daar staan en blijft alles werken. Bewust geen selector met
     *         // wire:submit erin -- die breekt zodra de modifier verandert.
     *         'widget_selector' => 'form button[type="submit"]',
     *         'widget_position' => 'before', // append, prepend, before of after
     *     ],
     *
     * Let op: een eigen config/captcha.php vervangt deze sleutel in zijn
     * geheel, en actions ook; neem daar 'default' => 'contact' dus mee.
     */
    'livewire' => [],

    /*
     * Wat te doen als de provider onbereikbaar is: doorlaten (true) of
     * weigeren (false). Geldt voor alle bewaakte formulieren. De verborgen
     * controles (honeypot en tijd) blijven in beide gevallen gelden.
     */
    'fail_open' => (bool) env('CAPTCHA_FAIL_OPEN', true),

    /*
     * Welk formulier welk token mag opleveren. Turnstile stuurt de action mee
     * terug bij siteverify, dus zo is een token van het ene formulier niet te
     * hergebruiken voor een ander. De sleutel is de context uit livewire
     * hierboven; 'default' geldt voor de routes in protect.
     *
     * Toegestaan: 1-32 tekens, letters, cijfers, _ en -.
     */
    'actions' => [
        'default' => 'contact',
    ],

    'providers' => [
        'turnstile' => [
            'sitekey' => env('TURNSTILE_SITE_KEY'),
            'secret' => env('TURNSTILE_SECRET_KEY'),

            /*
             * De hostnames waarop een token gemaakt mag zijn, kommagescheiden.
             * Siteverify geeft terug waar de bezoeker vandaan kwam; zonder deze
             * controle is een token dat op een ander toegestaan domein (denk aan
             * staging) is opgehaald ook hier geldig.
             *
             * Leeg laten slaat de controle over. Zet hier op productie nooit
             * localhost of 127.0.0.1 bij.
             */
            'hostnames' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('TURNSTILE_HOSTNAMES', ''))
            ))),
            'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'script_url' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'timeout' => 5,
        ],
    ],
];
