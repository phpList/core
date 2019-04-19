<?php
declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Template;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping;
use Doctrine\ORM\Mapping\Column;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Interfaces\Identity;
use PhpList\Core\Domain\Model\Traits\IdentityTrait;

/**
 * This class represents a message template which can be used for styling campaigns.
 *
 * @Mapping\Entity(repositoryClass="PhpList\Core\Domain\Repository\Template\TemplateRepository")
 * @Mapping\Table(name="phplist_template")
 * @Mapping\HasLifecycleCallbacks
 * @ExclusionPolicy("all")
 *
 * @author Sam Tuke <sam@phplist.com>
 */
class Template implements DomainModel, Identity
{
    use IdentityTrait;

    /**
     * @var int
     * @Column(name="id", unique=true)
     * @Expose
     */
    private $id = '';

    /**
     * @var string
     * @Column(type="varchar", name="title")
     * @Expose
     */
    private $title = '';

    /**
     * @var string
     * @Column(type="longblob", name="template")
     * @Expose
     */
    private $template = '';

    /**
     * @var int
     * @Column(type="integer", name="listorder")
     * @Expose
     */
    private $listPosition = 0;

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return void
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @param string $template
     *
     * @return void
     */
    public function setTemplate(string $template)
    {
        $this->template = $template;
    }

    /**
     * @return int
     */
    public function getListPosition(): int
    {
        return $this->listPosition;
    }

    /**
     * @param int $listPosition
     *
     * @return void
     */
    public function setListPosition(int $listPosition)
    {
        $this->listPosition = $listPosition;
    }
}
