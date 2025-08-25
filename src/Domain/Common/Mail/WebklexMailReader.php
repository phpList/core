<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Mail;

use Webklex\PHPIMAP\ClientManager;

class WebklexMailReader
{
    public function __construct(private ClientManager $cm, private array $config) {}


}
