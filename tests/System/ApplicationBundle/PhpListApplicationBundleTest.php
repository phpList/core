<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\System\ApplicationBundle;

use GuzzleHttp\Client;
use PhpList\Core\Tests\TestingSupport\Traits\SymfonyServerTrait;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class PhpListApplicationBundleTest extends TestCase
{
    use SymfonyServerTrait;

    private ?Client $httpClient = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = new Client(['http_errors' => false]);
    }

    protected function tearDown(): void
    {
        $this->stopSymfonyServer();
        $this->httpClient = null;
        parent::tearDown();
    }

    /**
     * @return string[][]
     */
    public function environmentDataProvider(): array
    {
        return [
            'test' => ['test'],
            'dev' => ['dev'],
        ];
    }

    /**
     * @param string $environment
     * @dataProvider environmentDataProvider
     */
    public function testHomepageReturnsSuccess(string $environment): void
    {
        $this->startSymfonyServer($environment);

        $response = $this->httpClient->get('/', ['base_uri' => $this->getBaseUrl()]);

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @param string $environment
     * @dataProvider environmentDataProvider
     */
    public function testHomepageReturnsDummyContent(string $environment): void
    {
        $this->startSymfonyServer($environment);

        $response = $this->httpClient->get('/', ['base_uri' => $this->getBaseUrl()]);

        self::assertStringContainsString(
            'This page has been intentionally left empty.',
            $response->getBody()->getContents()
        );
    }
}
