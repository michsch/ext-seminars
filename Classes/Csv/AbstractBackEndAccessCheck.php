<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Csv;

use OliverKlee\Oelib\Authentication\BackEndLoginManager;
use OliverKlee\Seminars\Csv\Interfaces\CsvAccessCheck;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * This class provides an access check for the CSV export in the back end.
 */
abstract class AbstractBackEndAccessCheck implements CsvAccessCheck
{
    /**
     * @var int
     *
     * @see BackendUtility::getRecord
     */
    private const SHOW_PAGE_PERMISSION_BITS = 1;

    /**
     * @var int
     */
    protected $pageUid = 0;

    /**
     * Sets the page UID of the records.
     *
     * @param int $pageUid the page UID of the records, must be >= 0
     */
    public function setPageUid(int $pageUid): void
    {
        $this->pageUid = $pageUid;
    }

    /**
     * Returns the page UID of the records to check.
     *
     * @return int the page UID, will be >= 0
     */
    protected function getPageUid(): int
    {
        return $this->pageUid;
    }

    /**
     * Checks whether the currently logged-in BE-User is allowed to access the given table and page.
     *
     * @param string $tableName the name of the table to check the read access for, must not be empty
     * @param int $pageUid the page to check the access for, must be >= 0
     *
     * @return bool TRUE if the user has access to the given table and page,
     *                 FALSE otherwise, will also return FALSE if no BE user is logged in
     */
    protected function canAccessTableAndPage(string $tableName, int $pageUid): bool
    {
        if (!BackEndLoginManager::getInstance()->isLoggedIn()) {
            return false;
        }

        return $this->hasReadAccessToTable($tableName) && $this->hasReadAccessToPage($pageUid);
    }

    protected function getLoggedInBackEndUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Checks whether the logged-in back-end user has read access to the table $tableName.
     *
     * @param string $tableName the table name to check, must not be empty
     */
    protected function hasReadAccessToTable(string $tableName): bool
    {
        return $this->getLoggedInBackEndUser()->check('tables_select', $tableName);
    }

    /**
     * Checks whether the logged-in back-end user has read access to the page (or folder) with the UID $pageUid.
     *
     * @param int $pageUid the page to check the access for, must be >= 0
     */
    protected function hasReadAccessToPage(int $pageUid): bool
    {
        return $this->getLoggedInBackEndUser()
            ->doesUserHaveAccess(BackendUtility::getRecord('pages', $pageUid), self::SHOW_PAGE_PERMISSION_BITS);
    }
}
