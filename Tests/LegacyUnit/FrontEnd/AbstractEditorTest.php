<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyUnit\FrontEnd;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Service\RegistrationManager;
use OliverKlee\Seminars\Tests\LegacyUnit\FrontEnd\Fixtures\TestingEditor;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * @covers \OliverKlee\Seminars\FrontEnd\AbstractEditor
 */
final class AbstractEditorTest extends TestCase
{
    /**
     * @var TestingEditor
     */
    private $subject;

    /**
     * @var TestingFramework
     */
    private $testingFramework;

    protected function setUp(): void
    {
        $this->testingFramework = new TestingFramework('tx_seminars');
        $rootPageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->changeRecord('pages', $rootPageUid, ['slug' => '/home']);
        $this->testingFramework->createFakeFrontEnd($rootPageUid);

        $this->subject = new TestingEditor([], $this->getFrontEndController()->cObj);
        $this->subject->setTestMode();
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUp();

        RegistrationManager::purgeInstance();
    }

    private function getFrontEndController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    //////////////////////////////
    // Testing the test mode flag
    //////////////////////////////

    /**
     * @test
     */
    public function isTestModeReturnsTrueForTestModeEnabled(): void
    {
        self::assertTrue(
            $this->subject->isTestMode()
        );
    }

    /**
     * @test
     */
    public function isTestModeReturnsFalseForTestModeDisabled(): void
    {
        $subject = new TestingEditor([], $this->getFrontEndController()->cObj);

        self::assertFalse(
            $subject->isTestMode()
        );
    }

    /////////////////////////////////////////////////
    // Tests for setting and getting the object UID
    /////////////////////////////////////////////////

    /**
     * @test
     */
    public function getObjectUidReturnsTheSetObjectUidForZero(): void
    {
        $this->subject->setObjectUid(0);

        self::assertEquals(
            0,
            $this->subject->getObjectUid()
        );
    }

    /**
     * @test
     */
    public function getObjectUidReturnsTheSetObjectUidForExistingObjectUid(): void
    {
        $uid = $this->testingFramework->createRecord('tx_seminars_test');
        $this->subject->setObjectUid($uid);

        self::assertEquals(
            $uid,
            $this->subject->getObjectUid()
        );
    }

    ////////////////////////////////////////////////////////////////
    // Tests for getting form values and setting faked form values
    ////////////////////////////////////////////////////////////////

    /**
     * @test
     */
    public function getFormValueReturnsEmptyStringForRequestedFormValueNotSet(): void
    {
        self::assertEquals(
            '',
            $this->subject->getFormValue('title')
        );
    }

    /**
     * @test
     */
    public function getFormValueReturnsValueSetViaSetFakedFormValue(): void
    {
        $this->subject->setFakedFormValue('title', 'foo');

        self::assertEquals(
            'foo',
            $this->subject->getFormValue('title')
        );
    }
}
