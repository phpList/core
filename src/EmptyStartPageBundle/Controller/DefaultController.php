<?php

declare(strict_types=1);

namespace PhpList\Core\EmptyStartPageBundle\Controller;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller provides an empty start page.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class DefaultController
{
    /**
     * An empty start page route.
     *
     * @throws InvalidArgumentException
     */
    #[Route('/api/v2', name: 'empty_start_page', methods: ['GET'])]
    public function index(): Response
    {
        return new Response('This page has been intentionally left empty.');
    }
}
