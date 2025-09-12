<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

class UserMessageRepository extends AbstractRepository
{
    public function findOneByUserAndMessage(Subscriber $subscriber, Message $campaign): ?UserMessage
    {
        return $this->findOneBy(['user' => $subscriber, 'message' => $campaign]);
    }
}
