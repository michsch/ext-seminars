<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyUnit\Csv;

use OliverKlee\Oelib\Authentication\BackEndLoginManager;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Csv\BackEndRegistrationAccessCheck;
use OliverKlee\Seminars\Csv\Interfaces\CsvAccessCheck;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

final class BackEndRegistrationAccessCheckTest extends TestCase
{
    /**
     * @var BackEndRegistrationAccessCheck
     */
    private $subject;

    /**
     * @var BackendUserAuthentication&MockObject
     */
    private $backEndUser;

    /**
     * @var BackendUserAuthentication
     */
    private $backEndUserBackup;

    /**
     * @var TestingFramework
     */
    private $testingFramework;

    protected function setUp(): void
    {
        $this->backEndUserBackup = $GLOBALS['BE_USER'];
        $backEndUser = $this->createMock(BackendUserAuthentication::class);
        $this->backEndUser = $backEndUser;
        $GLOBALS['BE_USER'] = $backEndUser;

        $this->testingFramework = new TestingFramework('tx_seminars');

        $this->subject = new BackEndRegistrationAccessCheck();
    }

    protected function tearDown(): void
    {
        BackEndLoginManager::purgeInstance();

        $this->testingFramework->cleanUp();
        $GLOBALS['BE_USER'] = $this->backEndUserBackup;
    }

    /**
     * @test
     */
    public function subjectImplementsAccessCheck(): void
    {
        self::assertInstanceOf(
            CsvAccessCheck::class,
            $this->subject
        );
    }

    /**
     * @test
     */
    public function hasAccessForNoBackEndUserReturnsFalse(): void
    {
        unset($GLOBALS['BE_USER']);

        self::assertFalse(
            $this->subject->hasAccess()
        );
    }

    /**
     * @test
     */
    public function hasAccessForNoAccessToEventsTableAndNoAccessToRegistrationsTableReturnsFalse(): void
    {
        $this->backEndUser->expects(self::at(0))->method('check')
            ->with('tables_select', 'tx_seminars_seminars')
            ->willReturn(false);

        self::assertFalse(
            $this->subject->hasAccess()
        );
    }

    /**
     * @test
     */
    public function hasAccessForNoAccessToEventsTableAndAccessToRegistrationsTableReturnsFalse(): void
    {
        $this->backEndUser->expects(self::at(0))->method('check')
            ->with('tables_select', 'tx_seminars_seminars')
            ->willReturn(false);

        self::assertFalse(
            $this->subject->hasAccess()
        );
    }

    /**
     * @test
     */
    public function hasAccessForAccessToEventsTableAndNoAccessToRegistrationsTableReturnsFalse(): void
    {
        $this->backEndUser->expects(self::at(0))->method('check')
            ->with('tables_select', 'tx_seminars_seminars')
            ->willReturn(true);
        $this->backEndUser->expects(self::at(1))->method('check')
            ->with('tables_select', 'tx_seminars_attendances')
            ->willReturn(false);

        self::assertFalse(
            $this->subject->hasAccess()
        );
    }

    /**
     * @test
     */
    public function hasAccessForAccessToEventsTableAndAccessToRegistrationsTableReturnsTrue(): void
    {
        $this->backEndUser->expects(self::at(0))->method('check')
            ->with('tables_select', 'tx_seminars_seminars')
            ->willReturn(true);
        $this->backEndUser->expects(self::at(1))->method('check')
            ->with('tables_select', 'tx_seminars_attendances')
            ->willReturn(true);

        self::assertTrue(
            $this->subject->hasAccess()
        );
    }

    /**
     * @test
     */
    public function hasAccessForAccessToEventsTableAndAccessToRegistrationsTableAndAccessToSetPageReturnsTrue(): void
    {
        $this->backEndUser->method('check')
            ->with('tables_select', self::anything())
            ->willReturn(true);

        $pageUid = 12341;
        $this->subject->setPageUid($pageUid);
        $pageRecord = BackendUtility::getRecord('pages', $pageUid);
        $this->backEndUser->method('doesUserHaveAccess')
            ->with($pageRecord, 1)
            ->willReturn(true);

        self::assertTrue(
            $this->subject->hasAccess()
        );
    }

    /**
     * @test
     */
    public function hasAccessForAccessToEventsTableAndAccessToRegistrationsTableAndNoAccessToSetPageReturnsFalse(): void
    {
        $this->backEndUser->method('check')
            ->with('tables_select', self::anything())
            ->willReturn(true);

        $pageUid = 12341;
        $this->subject->setPageUid($pageUid);
        $pageRecord = BackendUtility::getRecord('pages', $pageUid);
        $this->backEndUser->method('doesUserHaveAccess')
            ->with($pageRecord, 1)
            ->willReturn(false);

        self::assertFalse(
            $this->subject->hasAccess()
        );
    }
}
