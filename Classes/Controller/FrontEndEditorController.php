<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Controller;

use OliverKlee\Seminars\Domain\Model\Event\SingleEvent;
use OliverKlee\Seminars\Domain\Repository\Event\EventRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Plugin for editing single events in the FE.
 */
class FrontEndEditorController extends ActionController
{
    /**
     * @var EventRepository
     */
    private $eventRepository;

    public function injectEventRepository(EventRepository $repository): void
    {
        $this->eventRepository = $repository;
    }

    public function indexAction(): void
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $userUid = $context->getPropertyFromAspect('frontend.user', 'id');

        $events = $this->eventRepository->findSingleEventsByOwnerUid($userUid);
        $this->view->assign('events', $events);
    }

    /**
     * @TYPO3\CMS\Extbase\Annotation\IgnoreValidation("event")
     */
    public function editAction(SingleEvent $event): void
    {
        $this->view->assign('event', $event);
    }

    public function updateAction(SingleEvent $event): void
    {
        $this->eventRepository->update($event);
        $this->eventRepository->persistAll();

        $this->redirect('index');
    }
}