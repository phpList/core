<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service;

use PhpList\Core\Core\ParameterProvider;
use PhpList\Core\Domain\Analytics\Model\Dto\LinkTrackToken;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds and verifies the click-tracking URLs (`<rest api base url>/lt?tid=<token>`), the equivalent of
 * phplist3's lt.php links.
 *
 * The token is the url-safe base64 of `<H|T>|<forward uuid>|<message uuid>|<subscriber uuid>`. The uuids are
 * random, so a token cannot be guessed. With the `click_track_sign_with_hmac` parameter enabled the token is
 * additionally signed (`hm`) with the application secret, so a request can be rejected before touching the
 * database. Keep it off if the secret may be rotated, as that would invalidate the links of sent campaigns.
 *
 * Tokens of links in campaigns sent by phplist3 can be parsed as well, so that those links keep working when
 * lt.php is served by this application. Their HTML tokens are masked with the `xormask` setting, which is shared
 * through the config table; their text tokens are the three uuids packed as binary. phplist3 signs its links with
 * a hash of the whole url, which cannot be checked here, so with signing enabled they are refused.
 */
class LinkTrackTokenService
{
    public const ENDPOINT_PATH = '/lt';

    public function __construct(
        private readonly ParameterProvider $parameterProvider,
        #[Autowire('%app.rest_api_base_url%')] private readonly string $restApiBaseUrl,
        #[Autowire('%app.secret%')] private readonly string $secret,
        private readonly ConfigProvider $configProvider,
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
        if (!$this->hasValidSignature($tid, $signature)) {
            return null;
        }

        return $this->parseOwnFormat($tid) ?? $this->parsePhplist3Format($tid);
    }

    private function parseOwnFormat(string $tid): ?LinkTrackToken
    {
        $decoded = base64_decode(strtr($tid, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 4
            || !LinkTrackToken::isValidType($parts[0])
            || !$this->areValidUuids(array_slice($parts, 1))
        ) {
            return null;
        }

        return new LinkTrackToken($parts[0], $parts[1], $parts[2], $parts[3]);
    }

    /** The tokens of phplist3's lt.php, see its sendemaillib.php. */
    private function parsePhplist3Format(string $tid): ?LinkTrackToken
    {
        // a query string turns the + of the base64 into a space
        $tid = str_replace(' ', '+', $tid);

        if (strlen($tid) === 64) {
            $track = $this->unpackPhplist3TextToken($tid);
        } else {
            $track = $this->unmaskPhplist3HtmlToken($tid);
        }

        $uuid = '([a-f0-9]{8}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[89ab][a-f0-9]{3}-?[a-f0-9]{12})';
        $pattern = '/^(H|T)\|' . $uuid . '\|' . $uuid . '\|' . $uuid . '$/';
        if ($track === null || !preg_match($pattern, $track, $matches)) {
            return null;
        }

        return new LinkTrackToken($matches[1], $matches[2], $matches[3], $matches[4]);
    }

    /** Text links carry the forward, message and subscriber uuids as 48 binary bytes. */
    private function unpackPhplist3TextToken(string $tid): ?string
    {
        $binary = base64_decode($tid, true);
        if ($binary === false || strlen($binary) !== 48) {
            return null;
        }

        $hex = bin2hex($binary);
        $uuids = [];
        foreach ([0, 32, 64] as $offset) {
            // the version nibble of the uuid was replaced by random data, uuids are version 4
            $uuids[] = substr($hex, $offset, 8) . '-' . substr($hex, $offset + 8, 4)
                . '-4' . substr($hex, $offset + 13, 3) . '-' . substr($hex, $offset + 16, 4)
                . '-' . substr($hex, $offset + 20, 12);
        }

        return 'T|' . implode('|', $uuids);
    }

    /** HTML links carry `H|forward|message|subscriber` XOR-ed with the repeated xormask setting. */
    private function unmaskPhplist3HtmlToken(string $tid): ?string
    {
        $xorMask = $this->configProvider->getValue(ConfigOption::XorMask);
        $masked = base64_decode($tid);
        if ($xorMask === null || $xorMask === '' || $masked === '') {
            return null;
        }

        return $masked ^ str_repeat($xorMask, 20);
    }

    private function hasValidSignature(string $tid, ?string $signature): bool
    {
        if (!$this->isSigningEnabled()) {
            return true;
        }

        return $signature !== null && $signature !== '' && hash_equals($this->sign($tid), $signature);
    }

    /** @param string[] $uuids */
    private function areValidUuids(array $uuids): bool
    {
        foreach ($uuids as $uuid) {
            if (!preg_match('/^[A-Za-z0-9-]{1,64}$/', $uuid)) {
                return false;
            }
        }

        return true;
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
