<?php
declare(strict_types=1);

namespace PhpList\PhpList4\EmptyStartPageBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller provides an empty start page.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class DefaultController extends Controller
{
    /**
     * @Route("/")
     * @Method("GET")
     *
     * @return Response
     *
     * @throws \InvalidArgumentException
     */
    public function indexAction(): Response
    {
        return new Response('This page has been intentionally left empty.');
    }
}
