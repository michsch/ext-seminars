<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyUnit\BackEnd;

use OliverKlee\Oelib\Authentication\BackEndLoginManager;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\BackEnd\AbstractList;
use OliverKlee\Seminars\BackEnd\SpeakersList;
use OliverKlee\Seminars\Mapper\BackEndUserGroupMapper;
use OliverKlee\Seminars\Mapper\BackEndUserMapper;
use OliverKlee\Seminars\Tests\LegacyUnit\BackEnd\Fixtures\DummyModule;
use OliverKlee\Seminars\Tests\LegacyUnit\Support\Traits\BackEndTestsTrait;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Core\Information\Typo3Version;

final class SpeakersListTest extends TestCase
{
    use BackEndTestsTrait;

    /**
     * @var SpeakersList
     */
    private $subject;

    /**
     * @var TestingFramework
     */
    private $testingFramework;

    /**
     * @var int PID of a dummy system folder
     */
    private $dummySysFolderPid;

    protected function setUp(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 11) {
            self::markTestSkipped('Skipping because this code will be removed before adding 11LTS compatibility.');
        }

        $this->unifyTestingEnvironment();

        $this->testingFramework = new TestingFramework('tx_seminars');

        $this->dummySysFolderPid = $this->testingFramework->createSystemFolder();

        $backEndModule = new DummyModule();
        $backEndModule->id = $this->dummySysFolderPid;
        $backEndModule->setPageData(
            [
                'uid' => $this->dummySysFolderPid,
                'doktype' => AbstractList::SYSFOLDER_TYPE,
            ]
        );

        $backEndModule->doc = new DocumentTemplate();

        $this->subject = new SpeakersList($backEndModule);
    }

    protected function tearDown(): void
    {
        if ($this->testingFramework instanceof TestingFramework) {
            $this->testingFramework->cleanUp();
        }
        $this->restoreOriginalEnvironment();
    }

    /**
     * @test
     */
    public function showContainsHideButtonForVisibleSpeaker(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            [
                'pid' => $this->dummySysFolderPid,
                'hidden' => 0,
            ]
        );

        self::assertStringContainsString(
            'Icons/Hide.gif',
            $this->subject->show()
        );
    }

    /**
     * @test
     */
    public function showContainsUnhideButtonForHiddenSpeaker(): void
    {
        $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            [
                'pid' => $this->dummySysFolderPid,
                'hidden' => 1,
            ]
        );

        self::assertStringContainsString(
            'Icons/Unhide.gif',
            $this->subject->show()
        );
    }

    /**
     * @test
     */
    public function showContainsSpeakerFromSubfolder(): void
    {
        $subfolderPid = $this->testingFramework->createSystemFolder(
            $this->dummySysFolderPid
        );
        $this->testingFramework->createRecord(
            'tx_seminars_speakers',
            [
                'title' => 'Speaker in subfolder',
                'pid' => $subfolderPid,
            ]
        );

        self::assertStringContainsString(
            'Speaker in subfolder',
            $this->subject->show()
        );
    }

    //////////////////////////////////////
    // Tests concerning the "new" button
    //////////////////////////////////////

    /**
     * @test
     */
    public function newButtonForSpeakerStorageSettingSetInUsersGroupSetsThisPidAsNewRecordPid(): void
    {
        $newSpeakerFolder = $this->dummySysFolderPid + 1;
        $backEndGroup = MapperRegistry::get(BackEndUserGroupMapper::class)
            ->getLoadedTestingModel(['tx_seminars_auxiliaries_folder' => $newSpeakerFolder]);
        $backEndUser = MapperRegistry::get(BackEndUserMapper::class)
            ->getLoadedTestingModel(['usergroup' => $backEndGroup->getUid()]);
        BackEndLoginManager::getInstance()->setLoggedInUser($backEndUser);

        self::assertStringContainsString((string)$newSpeakerFolder, $this->subject->show());
    }
}
