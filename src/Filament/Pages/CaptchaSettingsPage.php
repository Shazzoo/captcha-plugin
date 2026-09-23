<?php

namespace Shazzoo\Captcha\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;
use Shazzoo\Captcha\Support\CaptchaSettings;
use Throwable;

class CaptchaSettingsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Captcha';

    protected static string|\UnitEnum|null $navigationGroup = 'Plugins';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Spambeveiliging';

    protected static ?string $slug = 'captcha';

    protected string $view = 'captcha::filament.pages.settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        /*
         * Velden met een placeholder mogen leeg blijven: daar laat de
         * placeholder zien wat de config doet. Een Toggle en een TagsInput
         * hebben die niet, dus die zouden als "uit" en "leeg" tonen terwijl de
         * config iets anders zegt -- en opslaan zou dat vervolgens waarmaken.
         * Daarom worden die met de werkelijke waarde gevuld.
         */
        $this->form->fill(array_merge([
            // Ook de provider: zonder waarde staat de Select leeg, en dan
            // verbergt visible() de sleutelvelden -- je kon ze dus niet
            // invullen terwijl Turnstile gewoon aanstond.
            'provider' => (string) config('captcha.provider'),
            'honeypot_enabled' => (bool) config('captcha.honeypot.enabled'),
            'protect' => (array) config('captcha.protect'),
            'fail_open_default' => (bool) config('captcha.fail_open.default'),
            'fail_open_scan' => (bool) config('captcha.fail_open.profiler-scan'),
        ], CaptchaSettings::all()));
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Verborgen controles')
                    ->collapsible()
                    ->description('Kosten niets en zijn voor een bezoeker onzichtbaar. Dit houdt het meeste bulkspam al tegen, zonder dat er iets naar buiten gaat.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('honeypot_enabled')
                            ->label('Aan')
                            ->columnSpanFull()
                            ->helperText('Een veld dat geen mens invult, plus een controle op hoe lang het formulier openstond.'),

                        TextInput::make('honeypot_field')
                            ->label('Veldnaam')
                            ->placeholder((string) config('captcha.honeypot.field'))
                            ->alphaDash()
                            ->helperText('De naam van het verborgen veld. Verander dit als een bot hem doorheeft.'),

                        TextInput::make('min_seconds')
                            ->label('Minimale invultijd in seconden')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder((string) config('captcha.honeypot.min_seconds'))
                            ->helperText('Sneller dan dit is geen mens. 0 zet deze controle uit. Let op: op een formulier met één veld is een haastige bezoeker zo onder de 2 seconden.'),

                        TextInput::make('max_minutes')
                            ->label('Maximale openstaan in minuten')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder((string) config('captcha.honeypot.max_minutes'))
                            ->helperText('Daarna vraagt het formulier om opnieuw te versturen. Een bezoeker met een tabblad open moet dat netjes te zien krijgen, niet een lege afwijzing.'),
                    ]),

                Section::make('Challenge')
                    ->collapsible()
                    ->description('Alleen nodig voor wat langs de controles hierboven komt. Turnstile stuurt het IP van de bezoeker naar Cloudflare, dus zet dit alleen aan als het nodig is -- en noem het in de privacyverklaring.')
                    ->columns(2)
                    ->schema([
                        Select::make('provider')
                            ->label('Aanbieder')
                            ->options([
                                'null' => 'Geen',
                                'turnstile' => 'Cloudflare Turnstile',
                            ])
                            ->live()
                            ->columnSpanFull()
                            ->placeholder(config('captcha.provider') === 'turnstile' ? 'Cloudflare Turnstile' : 'Geen')
                            ->helperText('Leeg laten gebruikt wat er in CAPTCHA_PROVIDER staat.'),

                        TextInput::make('sitekey')
                            ->label('Site key')
                            ->visible(fn ($get): bool => $get('provider') === 'turnstile')
                            ->placeholder(config('captcha.providers.turnstile.sitekey') ? 'Ingesteld via TURNSTILE_SITE_KEY' : 'Nog niet ingesteld')
                            ->helperText('Staat publiek in de HTML; dit is geen geheim.'),

                        TextInput::make('secret')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->visible(fn ($get): bool => $get('provider') === 'turnstile')
                            ->placeholder(config('captcha.providers.turnstile.secret') ? 'Ingesteld via TURNSTILE_SECRET_KEY' : 'Nog niet ingesteld')
                            ->helperText('Hier invullen of als TURNSTILE_SECRET_KEY in de .env; wat hier staat gaat voor en wordt versleuteld opgeslagen.'),
                    ]),

                Section::make('Waar het geldt')
                    ->collapsible()
                    ->description('Twee soorten formulieren, twee manieren van aanhaken. Samen is dit alles wat bewaakt wordt.')
                    ->schema([
                        TextEntry::make('livewire_guarded')
                            ->label('Livewire-componenten')
                            ->state(fn (): string => self::guardedComponents())
                            ->helperText('Deze acties lopen over het endpoint van Livewire en niet over een route, dus middleware helpt er niet. Ze staan in config/captcha.php van deze plugin: een methodenaam die niet klopt zou hier stil wegvallen, en dat is niets voor een invulveld.'),

                        TagsInput::make('protect')
                            ->label('Beschermde routes')
                            ->placeholder('Routenaam toevoegen')
                            ->helperText('Op routenaam, niet op pad, en alleen voor gewone POST-formulieren. Leeg betekent: geen enkel formulier via middleware beschermd.'),
                    ]),

                Section::make('Als Cloudflare onbereikbaar is')
                    ->collapsible()
                    ->description('Een storing bij de aanbieder mag niet stilletjes je formulieren dichtzetten -- en ook niet stilletjes je budget opmaken. Elke keer dat dit gebeurt komt in het log.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('fail_open_default')
                            ->label('Contactformulier doorlaten')
                            ->helperText('Aan: een gemiste aanvraag kost een klant, dus liever doorlaten. De verborgen controles gelden dan nog steeds.'),

                        Toggle::make('fail_open_scan')
                            ->label('Websitescan doorlaten')
                            ->helperText('Uit: elke scan kost geld bij de profiler, dus bij twijfel niet starten.'),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * De bewaakte Livewire-componenten, zodat op deze pagina te zien is wat er
     * werkelijk allemaal bewaakt wordt en niet alleen de helft die via routes
     * loopt.
     */
    private static function guardedComponents(): string
    {
        $lines = [];

        foreach ((array) config('captcha.livewire') as $name => $settings) {
            $methods = implode(', ', (array) ($settings['methods'] ?? []));

            $lines[] = $name.' -- '.($methods !== '' ? $methods : 'geen methoden ingesteld');
        }

        return $lines === [] ? 'Geen' : implode("\n", $lines);
    }

    public function save(): void
    {
        CaptchaSettings::save($this->form->getState());

        Notification::make()
            ->title('Instellingen opgeslagen')
            ->body('Een gewijzigde routelijst werkt pas na "php artisan optimize:clear" of een herstart.')
            ->success()
            ->send();
    }

    /**
     * Stuurt bewust een onzinnig token: Turnstile antwoordt dan met
     * invalid-input-response als het secret klopt, en met invalid-input-secret
     * als het niet klopt. Zo is de sleutel te testen zonder een echte bezoeker.
     */
    public function testKeys(): void
    {
        // Let op de ?? : een veld dat door visible() verborgen is, zit niet in
        // getState(). Zonder die val crashte deze knop op een verse installatie.
        $settings = $this->form->getState();
        $secret = ($settings['secret'] ?? null) ?: config('captcha.providers.turnstile.secret');

        if (! $secret) {
            Notification::make()->title('Vul eerst een secret key in')->warning()->send();

            return;
        }

        try {
            $body = Http::asForm()
                ->timeout(10)
                ->post((string) config('captcha.providers.turnstile.verify_url'), [
                    'secret' => $secret,
                    'response' => 'test-token-that-cannot-be-valid',
                ])
                ->json();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Cloudflare niet bereikbaar')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $errors = (array) ($body['error-codes'] ?? []);

        match (true) {
            in_array('invalid-input-response', $errors, true) => Notification::make()
                ->title('Secret key werkt')
                ->body('Cloudflare wees alleen het testtoken af, niet de sleutel.')
                ->success()
                ->send(),

            in_array('invalid-input-secret', $errors, true) || in_array('missing-input-secret', $errors, true) => Notification::make()
                ->title('Secret key wordt geweigerd')
                ->danger()
                ->send(),

            default => Notification::make()
                ->title('Onverwacht antwoord')
                ->body(implode(', ', $errors) ?: 'geen foutcodes')
                ->warning()
                ->send(),
        };
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Opslaan')
                ->icon('heroicon-o-check')
                ->keyBindings(['mod+s'])
                ->action('save'),

            Action::make('testKeys')
                ->label('Sleutels testen')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->visible(fn (): bool => ($this->data['provider'] ?? config('captcha.provider')) === 'turnstile')
                ->action('testKeys'),
        ];
    }
}
