<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\EmptyStartPageBundle\Controller;

use PhpList\PhpList4\EmptyStartPageBundle\Controller\DefaultController;
use PhpList\PhpList4\TestingSupport\AbstractWebTest;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class DefaultControllerTest extends AbstractWebTest
{
    /**
     * @test
     */
    public function controllerIsAvailableViaContainer()
    {
        static::assertInstanceOf(
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

        static::assertTrue($this->client->getResponse()->isSuccessful());
        static::assertContains(
            'This page has been intentionally left empty.',
            $this->client->getResponse()->getContent()
        );
    }
}
