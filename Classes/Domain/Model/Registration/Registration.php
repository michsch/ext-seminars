<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Model\Registration;

use OliverKlee\FeUserExtraFields\Domain\Model\FrontendUser;
use OliverKlee\Seminars\Domain\Model\Event\Event;
use OliverKlee\Seminars\Domain\Model\Event\EventDate;
use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use TYPO3\CMS\Extbase\Annotation as Extbase;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;

/**
 * This class represents a registration (or a waiting list entry) for an event.
 */
class Registration extends AbstractEntity
{
    use AttendeesTrait;
    use BillingAddressTrait;
    use PaymentTrait;

    /**
     * @var string
     * @Extbase\Validate("StringLength", options={"maximum": 255})
     */
    protected $title = '';

    /**
     * @var \OliverKlee\Seminars\Domain\Model\Event\Event|null
     * @phpstan-var Event|LazyLoadingProxy|null
     * @TYPO3\CMS\Extbase\Annotation\ORM\Lazy
     */
    protected $event;

    /**
     * @var bool
     */
    protected $onWaitingList = false;

    /**
     * @var string
     * @Extbase\Validate("StringLength", options={"maximum": 16383})
     */
    protected $interests = '';

    /**
     * @var string
     * @Extbase\Validate("StringLength", options={"maximum": 16383})
     */
    protected $expectations = '';

    /**
     * @var string
     * @Extbase\Validate("StringLength", options={"maximum": 16383})
     */
    protected $comments = '';

    /**
     * @var string
     * @Extbase\Validate("StringLength", options={"maximum": 16383})
     */
    protected $knownFrom = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $name): void
    {
        $this->title = $name;
    }

    public function getEvent(): ?Event
    {
        $event = $this->event;
        if ($event instanceof LazyLoadingProxy) {
            $event = $event->_loadRealInstance();
            \assert($event instanceof Event);
            $this->event = $event;
        }

        return $event;
    }

    public function setEvent(Event $event): void
    {
        $this->event = $event;
    }

    /**
     * Checks whether the associated event is set and of a type to which someone actually can register
     * (a single event or an event date, but not an event topic).
     */
    public function hasValidEventType(): bool
    {
        $event = $this->getEvent();

        return $event instanceof SingleEvent || $event instanceof EventDate;
    }

    /**
     * Checks whether all associations are set in a way that this registration can be used.
     *
     * This safeguards against cases where an event or a user is deleted, but the registration is not.
     */
    public function hasNecessaryAssociations(): bool
    {
        return $this->hasValidEventType() && $this->getUser() instanceof FrontendUser;
    }

    public function isOnWaitingList(): bool
    {
        return $this->onWaitingList;
    }

    public function setOnWaitingList(bool $onWaitingList): void
    {
        $this->onWaitingList = $onWaitingList;
    }

    public function getInterests(): string
    {
        return $this->interests;
    }

    public function setInterests(string $interests): void
    {
        $this->interests = $interests;
    }

    public function getExpectations(): string
    {
        return $this->expectations;
    }

    public function setExpectations(string $expectations): void
    {
        $this->expectations = $expectations;
    }

    public function getComments(): string
    {
        return $this->comments;
    }

    public function setComments(string $comments): void
    {
        $this->comments = $comments;
    }

    public function getKnownFrom(): string
    {
        return $this->knownFrom;
    }

    public function setKnownFrom(string $knownFrom): void
    {
        $this->knownFrom = $knownFrom;
    }
}
