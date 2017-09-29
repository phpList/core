<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\EmptyStartPageBundle\Controller;

use PhpList\PhpList4\EmptyStartPageBundle\Controller\DefaultController;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class DefaultControllerTest extends TestCase
{
    /**
     * @var DefaultController
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new DefaultController();
    }

    /**
     * @test
     */
    public function classIsController()
    {
        self::assertInstanceOf(Controller::class, $this->subject);
    }

    /**
     * @test
     */
    public function indexActionReturnsResponseWithHelloWorld()
    {
        $result = $this->subject->indexAction();

        $expectedResult = new Response('This page has been intentionally left empty.');
        self::assertEquals($expectedResult, $result);
    }
}
