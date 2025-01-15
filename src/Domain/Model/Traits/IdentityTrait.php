<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * This trait provides an ID property to domain models.
 *
 * This is the default implementation of the Identity interface.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
trait IdentityTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    #[SerializedName('id')]
    #[Groups(['SubscriberList', 'SubscriberListMembers'])]
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}
