<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\EmptyStartPageBundle\Controller;

use PhpList\Core\EmptyStartPageBundle\Controller\DefaultController;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class DefaultControllerTest extends TestCase
{
    private DefaultController $subject;

    protected function setUp(): void
    {
        $this->subject = new DefaultController();
    }

    public function testClassIsController(): void
    {
        self::assertInstanceOf(AbstractController::class, $this->subject);
    }

    public function testIndexActionReturnsResponseWithHelloWorld(): void
    {
        $result = $this->subject->index();

        $expectedResult = new Response('This page has been intentionally left empty.');
        self::assertEquals($expectedResult, $result);
    }
}
