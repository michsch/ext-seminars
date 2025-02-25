<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Csv;

use OliverKlee\Oelib\Authentication\BackEndLoginManager;

/**
 * This class provides the access check for the CSV export of registrations in the back end.
 */
class BackEndRegistrationAccessCheck extends AbstractBackEndAccessCheck
{
    /**
     * @var string
     */
    private const TABLE_NAME_EVENTS = 'tx_seminars_seminars';

    /**
     * @var string
     */
    private const TABLE_NAME_REGISTRATIONS = 'tx_seminars_attendances';

    /**
     * Checks whether the logged-in user (if any) in the current environment has access to a CSV export.
     *
     * If a page UID has been set, this method also checks that the user has read access to that page.
     *
     * @return bool whether the logged-in user (if any) in the current environment has access to a CSV export.
     */
    public function hasAccess(): bool
    {
        if (!BackEndLoginManager::getInstance()->isLoggedIn()) {
            return false;
        }

        $hasAccessToPage = ($this->getPageUid() !== 0) ? $this->hasReadAccessToPage($this->getPageUid()) : true;

        return $hasAccessToPage && $this->hasReadAccessToTable(self::TABLE_NAME_EVENTS)
            && $this->hasReadAccessToTable(self::TABLE_NAME_REGISTRATIONS);
    }
}
