<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\EmptyStartPageBundle\Controller;

use PhpList\Core\EmptyStartPageBundle\Controller\DefaultController;
use PhpList\Core\Tests\TestingSupport\AbstractWebTest;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class DefaultControllerTest extends AbstractWebTest
{
    public function testControllerIsAvailableViaContainer()
    {
        self::assertInstanceOf(
            DefaultController::class,
            $this->client->getContainer()->get(DefaultController::class)
        );
    }

    /**
     * @test
     */
    public function indexActionReturnsResponseWithHelloWorld()
    {
        $this->client->request('GET', '/');

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertContains(
            'This page has been intentionally left empty.',
            $this->client->getResponse()->getContent()
        );
    }
}
