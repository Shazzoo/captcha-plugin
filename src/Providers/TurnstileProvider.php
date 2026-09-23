<?php

namespace Shazzoo\Captcha\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Shazzoo\Captcha\Contracts\CaptchaProvider;

class TurnstileProvider implements CaptchaProvider
{
    public function __construct(private readonly array $config) {}

    public function tokenField(): string
    {
        return 'cf-turnstile-response';
    }

    public function verify(?string $token, Request $request, ?string $expectedAction = null): bool
    {
        // Geen token betekent dat de widget niet gedraaid heeft: een afwijzing,
        // geen storing. Niet als fail_open behandelen.
        if (blank($token)) {
            return false;
        }

        $response = Http::asForm()
            ->timeout((int) ($this->config['timeout'] ?? 5))
            ->post($this->config['verify_url'], array_filter([
                'secret' => $this->config['secret'],
                'response' => $token,
                // Helpt Cloudflare bij scoring. Klopt alleen als trusted proxies
                // goed staan; zie de notitie in het plan.
                'remoteip' => $request->ip(),
                'idempotency_key' => (string) $request->attributes->get('captcha_idempotency_key'),
            ]));

        if ($response->failed()) {
            throw new RuntimeException('Turnstile siteverify returned HTTP '.$response->status());
        }

        $body = $response->json();

        if (! is_array($body) || ! array_key_exists('success', $body)) {
            throw new RuntimeException('Turnstile siteverify returned an unreadable body.');
        }

        if ($body['success'] === true) {
            return $this->matchesAction($body, $expectedAction)
                && $this->matchesHostname($body);
        }

        /*
         * Foutcodes die op onze configuratie wijzen zijn geen bot: een
         * verkeerd secret moet als storing gelden, anders blokkeren we stilletjes
         * iedereen zodra iemand een sleutel verkeerd overtypt.
         */
        $ours = ['missing-input-secret', 'invalid-input-secret', 'bad-request', 'internal-error'];

        if (array_intersect($ours, (array) ($body['error-codes'] ?? []))) {
            throw new RuntimeException(
                'Turnstile rejected our configuration: '.implode(', ', (array) $body['error-codes'])
            );
        }

        return false;
    }

    /**
     * Turnstile echoot de action van de widget terug. Klopt die niet, dan is
     * het token op een ander formulier opgehaald -- precies de manier om een
     * goedkope pagina te gebruiken als sleutel voor een dure.
     */
    private function matchesAction(array $body, ?string $expectedAction): bool
    {
        if ($expectedAction === null || $expectedAction === '') {
            return true;
        }

        if (($body['action'] ?? null) === $expectedAction) {
            return true;
        }

        Log::info('Captcha rejected a token for the wrong action', [
            'expected' => $expectedAction,
            'received' => $body['action'] ?? null,
        ]);

        return false;
    }

    /** Waar het token vandaan kwam; leeg gelaten slaat de controle over. */
    private function matchesHostname(array $body): bool
    {
        $allowed = (array) ($this->config['hostnames'] ?? []);

        if ($allowed === []) {
            return true;
        }

        if (in_array($body['hostname'] ?? null, $allowed, true)) {
            return true;
        }

        Log::info('Captcha rejected a token from an unexpected hostname', [
            'received' => $body['hostname'] ?? null,
        ]);

        return false;
    }
}
