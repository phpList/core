<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\System\ApplicationBundle;

use GuzzleHttp\Client;
use PhpList\Core\TestingSupport\Traits\SymfonyServerTrait;
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

        putenv('APP_ENV=test');
        $_ENV['APP_ENV'] = 'test';
        $_SERVER['APP_ENV'] = 'test';
        $this->httpClient = new Client(['http_errors' => false]);
    }

    protected function tearDown(): void
    {
        $this->stopSymfonyServer();
        $this->httpClient = null;
        parent::tearDown();
    }

    public function testHomepageReturnsSuccess(): void
    {
        $this->startSymfonyServer();
        $response = $this->httpClient->get('/api/v2', [
            'base_uri' => $this->getBaseUrl(),
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(
            'This page has been intentionally left empty.',
            $response->getBody()->getContents()
        );
    }
}
