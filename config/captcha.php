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
     * error_field: het validatieveld waar de melding op komt te staan, zodat
     * die verschijnt in de foutweergave die het component toch al heeft.
     */
    'livewire' => [
        'profiler.scan' => [
            'methods' => ['submit'],
            'error_field' => 'domain',
            'context' => 'profiler-scan',

            /*
             * Waar de widget terechtkomt. Zonder dit hangt hij onderaan de
             * pagina, want het bewaakte component plaatst hem niet zelf -- dat
             * is nu juist het punt. Een CSS-selector op deze pagina; de widget
             * wordt daar na het renderen naartoe verplaatst. Matcht de selector
             * niets, dan blijft hij onderaan staan en blijft alles werken.
             *
             * Standaard vlak boven de verstuurknop: dat is waar een bezoeker
             * hem verwacht. Bewust geen selector met wire:submit erin -- die
             * breekt zodra de modifier (.prevent) verandert.
             */
            'widget_selector' => 'form button[type="submit"]',
            'widget_position' => 'before', // append, prepend, before of after
        ],
    ],

    /*
     * Wat te doen als de provider onbereikbaar is. Per formulier, want de
     * afweging verschilt: een gemiste contactaanvraag kost een lead, een
     * ongecontroleerde scan kost scraping- en LLM-spend bij de profiler.
     */
    'fail_open' => [
        'default' => true,
        'profiler-scan' => false,
    ],

    /*
     * Welk formulier welk token mag opleveren. Turnstile stuurt de action mee
     * terug bij siteverify, dus zo is een token van het contactformulier niet
     * te hergebruiken voor een scan -- en juist die kost geld.
     *
     * Toegestaan: 1-32 tekens, letters, cijfers, _ en -.
     */
    'actions' => [
        'default' => 'contact',
        'profiler-scan' => 'scan',
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
