<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Analytics\Service\Manager;

use PhpList\Core\Domain\Analytics\Repository\UserMessageViewRepository;

class UserMessageViewManager
{
    private UserMessageViewRepository $userMessageViewRepository;

    public function __construct(
        UserMessageViewRepository $userMessageViewRepository,
    ) {
        $this->userMessageViewRepository = $userMessageViewRepository;
    }

    /**
     * Count views by message ID
     */
    public function countViewsByMessageId(int $messageId): int
    {
        return $this->userMessageViewRepository->countByMessageId($messageId);
    }
}
