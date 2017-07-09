<?php
declare(strict_types=1);

namespace PhpList\PhpList4\ApplicationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller is a placeholder that will be removed once there is a REST controller.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class DefaultController extends Controller
{
    /**
     * @return Response
     *
     * @throws \InvalidArgumentException
     */
    public function indexAction(): Response
    {
        return new Response('Hello world!');
    }
}
