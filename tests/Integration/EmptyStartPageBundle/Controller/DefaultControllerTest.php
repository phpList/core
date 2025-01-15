<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\EmptyStartPageBundle\Controller;

use PhpList\Core\EmptyStartPageBundle\Controller\DefaultController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class DefaultControllerTest extends WebTestCase
{
    public function testControllerIsAvailableViaContainer(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        self::assertInstanceOf(
            DefaultController::class,
            $container->get(DefaultController::class)
        );
    }

    public function testIndexActionReturnsResponseWithHelloWorld(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/v2');

        $response = $client->getResponse();

        self::assertTrue($response->isSuccessful());
        self::assertStringContainsString(
            'This page has been intentionally left empty.',
            $response->getContent()
        );
    }
}
