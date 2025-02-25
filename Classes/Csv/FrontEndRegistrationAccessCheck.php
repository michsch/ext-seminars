<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Csv;

use OliverKlee\Oelib\Authentication\FrontEndLoginManager;
use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Seminars\Csv\Interfaces\CsvAccessCheck;
use OliverKlee\Seminars\Mapper\FrontEndUserMapper;
use OliverKlee\Seminars\OldModel\LegacyEvent;

/**
 * This class provides the access check for the CSV export of registrations in the front end.
 */
class FrontEndRegistrationAccessCheck implements CsvAccessCheck
{
    /**
     * @var LegacyEvent|null
     */
    protected $event;

    /**
     * Sets the event for the access check.
     *
     * @param LegacyEvent $event
     */
    public function setEvent(LegacyEvent $event): void
    {
        $this->event = $event;
    }

    /**
     * Returns the event for the access check.
     */
    protected function getEvent(): ?LegacyEvent
    {
        return $this->event;
    }

    /**
     * Checks whether the logged-in user (if any) in the current environment has access to a CSV export.
     *
     * @return bool whether the logged-in user (if any) in the current environment has access to a CSV export.
     *
     * @throws \BadMethodCallException
     */
    public function hasAccess(): bool
    {
        if ($this->getEvent() === null) {
            throw new \BadMethodCallException('Please set an event first.', 1389096647);
        }
        $loginManager = FrontEndLoginManager::getInstance();
        if (!$loginManager->isLoggedIn()) {
            return false;
        }

        $configuration = ConfigurationRegistry::get('plugin.tx_seminars_pi1');
        if (!$configuration->getAsBoolean('allowCsvExportOfRegistrationsInMyVipEventsView')) {
            return false;
        }

        $user = MapperRegistry::get(FrontEndUserMapper::class)->find($loginManager->getLoggedInUserUid());
        $vipsGroupUid = $configuration->getAsInteger('defaultEventVipsFeGroupID');

        return $this->getEvent()->isUserVip($user->getUid(), $vipsGroupUid);
    }
}
