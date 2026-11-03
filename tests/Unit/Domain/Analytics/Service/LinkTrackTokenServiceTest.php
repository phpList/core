<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Analytics\Service;

use PhpList\Core\Core\ParameterProvider;
use PhpList\Core\Domain\Analytics\Model\Dto\LinkTrackToken;
use PhpList\Core\Domain\Analytics\Service\LinkTrackTokenService;
use PhpList\Core\Domain\Configuration\Model\ConfigOption;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PHPUnit\Framework\TestCase;

class LinkTrackTokenServiceTest extends TestCase
{
    private const XOR_MASK = '0123456789abcdef0123456789abcdef';
    private const FORWARD_UUID = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';
    private const MESSAGE_UUID = '11111111-2222-4333-a444-555555555555';
    private const SUBSCRIBER_UUID = '99999999-8888-4777-b666-444444444444';

    private function createService(
        bool $sign = false,
        string $secret = 'secret',
        ?string $xorMask = self::XOR_MASK
    ): LinkTrackTokenService {
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('getValue')->with(ConfigOption::XorMask)->willReturn($xorMask);

        return new LinkTrackTokenService(
            new ParameterProvider(['click_track_sign_with_hmac' => $sign]),
            'https://example.com/api/v2/',
            $secret,
            $configProvider
        );
    }

    /** What phplist3's sendemaillib.php puts in the link of an HTML email. */
    private function phplist3HtmlToken(): string
    {
        $track = 'H|' . self::FORWARD_UUID . '|' . self::MESSAGE_UUID . '|' . self::SUBSCRIBER_UUID;
        $masked = base64_encode($track ^ str_repeat(self::XOR_MASK, 20));

        return preg_replace('/=$/', '', preg_replace('/=$/', '', $masked));
    }

    /** What phplist3's sendemaillib.php puts in the link of a text email. */
    private function phplist3TextToken(): string
    {
        $masked = self::FORWARD_UUID . self::MESSAGE_UUID . self::SUBSCRIBER_UUID;
        foreach ([14, 36 + 14, 72 + 14] as $position) {
            $masked[$position] = 'c';
        }

        return str_replace('=', '', base64_encode(hex2bin(str_replace('-', '', $masked))));
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

    public function testParsesTheHtmlTokensOfPhplist3(): void
    {
        $token = $this->createService()->parse($this->phplist3HtmlToken());

        self::assertNotNull($token);
        self::assertTrue($token->isHtml());
        self::assertSame(self::FORWARD_UUID, $token->forwardUuid);
        self::assertSame(self::MESSAGE_UUID, $token->messageUuid);
        self::assertSame(self::SUBSCRIBER_UUID, $token->subscriberUuid);
    }

    public function testParsesTheHtmlTokensOfPhplist3AfterAQueryStringTurnedPlusIntoSpace(): void
    {
        $tid = str_replace('+', ' ', $this->phplist3HtmlToken());

        self::assertNotNull($this->createService()->parse($tid));
    }

    public function testParsesTheTextTokensOfPhplist3(): void
    {
        $token = $this->createService()->parse($this->phplist3TextToken());

        self::assertNotNull($token);
        self::assertSame('T', $token->type);
        self::assertSame(self::FORWARD_UUID, $token->forwardUuid);
        self::assertSame(self::MESSAGE_UUID, $token->messageUuid);
        self::assertSame(self::SUBSCRIBER_UUID, $token->subscriberUuid);
    }

    public function testPhplist3HtmlTokensNeedTheSameXorMask(): void
    {
        $tid = $this->phplist3HtmlToken();

        self::assertNull($this->createService(xorMask: 'fedcba9876543210fedcba9876543210')->parse($tid));
        self::assertNull($this->createService(xorMask: null)->parse($tid));
    }

    public function testPhplist3TokensCannotBeVerifiedWhenSigningIsEnabled(): void
    {
        $service = $this->createService(sign: true);

        self::assertNull($service->parse($this->phplist3HtmlToken(), 'whatever'));
        self::assertNull($service->parse($this->phplist3TextToken(), 'whatever'));
    }
}
