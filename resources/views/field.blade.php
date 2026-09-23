{{--
    Voor gewone POST-formulieren. Livewire-componenten worden niet zo bediend:
    die worden vanuit config('captcha.livewire') bewaakt en krijgen hun widget
    van InjectCaptchaWidget, zodat het component zelf niets hoeft te plaatsen.

    Het honeypot-veld staat buiten beeld in plaats van op display:none: die
    wordt door een deel van de screenreaders gewoon voorgelezen, en een blinde
    bezoeker die hem invult wordt dan zonder uitleg geweigerd.
--}}
<div>
    <div aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;">
        <label for="{{ $honeypotField() }}">Laat dit veld leeg</label>
        <input type="text" id="{{ $honeypotField() }}" name="{{ $honeypotField() }}" tabindex="-1" autocomplete="off" value="" />
    </div>

    <input type="hidden" name="{{ $timeField() }}" value="{{ $signedTimestamp() }}" />

    @if ($sitekey())
        <div class="captcha-widget" data-captcha-turnstile>
            <div data-captcha-target data-sitekey="{{ $sitekey() }}" @if ($action()) data-action="{{ $action() }}" @endif></div>
            <input type="hidden" name="cf-turnstile-response" data-captcha-token />
        </div>

        @once
            {{-- Los bestand in plaats van inline: houdt een strikte CSP
                 mogelijk. Beide scripts op defer, want die draaien in
                 documentvolgorde -- api.js roept captchaRenderWidgets aan en
                 die moet dus als eerste geladen zijn. --}}
            <script src="{{ asset('vendor/captcha/captcha.js') }}" defer></script>
            <script src="{{ $scriptUrl() }}?render=explicit&onload=captchaRenderWidgets" defer></script>
        @endonce
    @endif
</div>
