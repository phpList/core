<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Analytics\Service;

use PhpList\Core\Core\ParameterProvider;
use PhpList\Core\Domain\Analytics\Model\Dto\LinkTrackToken;
use PhpList\Core\Domain\Analytics\Service\LinkTrackTokenService;
use PHPUnit\Framework\TestCase;

class LinkTrackTokenServiceTest extends TestCase
{
    private function createService(bool $sign = false, string $secret = 'secret'): LinkTrackTokenService
    {
        return new LinkTrackTokenService(
            new ParameterProvider(['click_track_sign_with_hmac' => $sign]),
            'https://example.com/api/v2/',
            $secret
        );
    }

    /** @return array<string, string> */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    public function testCreatesAbsoluteUrlToTheLinkTrackEndpoint(): void
    {
        $url = $this->createService()->createUrl(new LinkTrackToken('H', 'fwd-1', 'msg-2', 'usr-3'));

        self::assertStringStartsWith('https://example.com/api/v2/lt?tid=', $url);
        self::assertSame(['tid'], array_keys($this->queryOf($url)));
    }

    public function testParsesWhatItCreated(): void
    {
        $service = $this->createService();
        $url = $service->createUrl(new LinkTrackToken('T', 'fwd-1', 'msg-2', 'usr-3'));

        $token = $service->parse($this->queryOf($url)['tid']);

        self::assertNotNull($token);
        self::assertSame('T', $token->type);
        self::assertFalse($token->isHtml());
        self::assertSame('fwd-1', $token->forwardUuid);
        self::assertSame('msg-2', $token->messageUuid);
        self::assertSame('usr-3', $token->subscriberUuid);
    }

    /** @dataProvider malformedTokenProvider */
    public function testRejectsMalformedTokens(string $tid): void
    {
        self::assertNull($this->createService()->parse($tid));
    }

    public static function malformedTokenProvider(): array
    {
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        return [
            'not base64' => ['!!!'],
            'too few parts' => [$encode('H|a|b')],
            'too many parts' => [$encode('H|a|b|c|d')],
            'unknown type' => [$encode('X|a|b|c')],
            'illegal characters' => [$encode("H|a|b|c'; drop table")],
            'empty uuid' => [$encode('H||b|c')],
        ];
    }

    public function testSignedUrlsAreVerified(): void
    {
        $service = $this->createService(sign: true);
        $query = $this->queryOf($service->createUrl(new LinkTrackToken('H', 'f', 'm', 'u')));

        self::assertArrayHasKey('hm', $query);
        self::assertNotNull($service->parse($query['tid'], $query['hm']));
        self::assertNull($service->parse($query['tid']), 'missing signature');
        self::assertNull($service->parse($query['tid'], 'wrong'), 'wrong signature');
        self::assertNull(
            $this->createService(sign: true, secret: 'other')->parse($query['tid'], $query['hm']),
            'signature of another secret'
        );
    }

    public function testUnsignedUrlsHaveNoSignature(): void
    {
        $query = $this->queryOf($this->createService()->createUrl(new LinkTrackToken('H', 'f', 'm', 'u')));

        self::assertArrayNotHasKey('hm', $query);
    }
}
