{{--
    Wordt door InjectCaptchaWidget vlak voor </body> gezet op pagina's met een
    bewaakt Livewire-component. Het honeypot-veld staat buiten beeld in plaats
    van op display:none: die wordt door een deel van de screenreaders gewoon
    voorgelezen.
--}}
<div data-captcha-livewire
     data-sitekey="{{ $sitekey }}"
     data-trap-field="{{ $trapField }}"
     data-timestamp="{{ $timestamp }}"
     @if ($mountSelector) data-mount-selector="{{ $mountSelector }}" data-mount-position="{{ $mountPosition }}" @endif>

    <div aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;">
        <label for="{{ $trapField }}">Laat dit veld leeg</label>
        <input type="text" id="{{ $trapField }}" name="{{ $trapField }}" data-captcha-trap tabindex="-1" autocomplete="off" value="" />
    </div>

    @if ($sitekey)
        <div data-captcha-target data-sitekey="{{ $sitekey }}" @if ($action) data-action="{{ $action }}" @endif></div>
    @endif
</div>

@if ($sitekey)
    <script src="{{ asset('vendor/captcha/captcha.js') }}" defer></script>
    <script src="{{ $scriptUrl }}?render=explicit&onload=captchaRenderWidgets" defer></script>
@else
    {{-- Zonder provider is er niets te renderen, maar de headers met honeypot
         en tijd moeten nog wel mee. --}}
    <script src="{{ asset('vendor/captcha/captcha.js') }}" defer></script>
@endif
