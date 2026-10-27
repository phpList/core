<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

use PhpList\Core\Core\ParameterProvider;
use PhpList\Core\Domain\Analytics\Model\Dto\LinkTrackToken;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds and verifies the click-tracking URLs (`<rest api base url>/lt?tid=<token>`), the equivalent of
 * phplist3's lt.php links.
 *
 * The token is the url-safe base64 of `<H|T>|<forward uuid>|<message uuid>|<subscriber uuid>`. The uuids are
 * random, so a token cannot be guessed. With the `click_track_sign_with_hmac` parameter enabled the token is
 * additionally signed (`hm`) with the application secret, so a request can be rejected before touching the
 * database. Keep it off if the secret may be rotated, as that would invalidate the links of sent campaigns.
 */
class LinkTrackTokenService
{
    public const ENDPOINT_PATH = '/lt';

    public function __construct(
        private readonly ParameterProvider $parameterProvider,
        #[Autowire('%app.rest_api_base_url%')] private readonly string $restApiBaseUrl,
        #[Autowire('%app.secret%')] private readonly string $secret,
    ) {
    }

    public function createUrl(LinkTrackToken $token): string
    {
        $encoded = rtrim(strtr(base64_encode(implode('|', [
            $token->type,
            $token->forwardUuid,
            $token->messageUuid,
            $token->subscriberUuid,
        ])), '+/', '-_'), '=');

        $url = rtrim($this->restApiBaseUrl, '/') . self::ENDPOINT_PATH . '?tid=' . $encoded;
        if ($this->isSigningEnabled()) {
            $url .= '&hm=' . $this->sign($encoded);
        }

        return $url;
    }

    /** Returns null for malformed tokens and, when signing is enabled, for missing or wrong signatures. */
    public function parse(string $tid, ?string $signature = null): ?LinkTrackToken
    {
        if ($this->isSigningEnabled()
            && ($signature === null || $signature === '' || !hash_equals($this->sign($tid), $signature))
        ) {
            return null;
        }

        $decoded = base64_decode(strtr($tid, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 4 || !LinkTrackToken::isValidType($parts[0])) {
            return null;
        }

        foreach (array_slice($parts, 1) as $uuid) {
            if (!preg_match('/^[A-Za-z0-9-]{1,64}$/', $uuid)) {
                return null;
            }
        }

        return new LinkTrackToken($parts[0], $parts[1], $parts[2], $parts[3]);
    }

    private function isSigningEnabled(): bool
    {
        return (bool) $this->parameterProvider->get('click_track_sign_with_hmac', false);
    }

    private function sign(string $encodedToken): string
    {
        return hash_hmac('sha256', $encodedToken, $this->secret);
    }
}
