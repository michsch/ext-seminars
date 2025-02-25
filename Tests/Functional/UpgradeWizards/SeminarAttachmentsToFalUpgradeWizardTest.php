<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\UpgradeWizards;

use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use OliverKlee\Seminars\Tests\Functional\Traits\FalHelper;
use OliverKlee\Seminars\UpgradeWizards\SeminarAttachmentsToFalUpgradeWizard;

/**
 * @covers \OliverKlee\Seminars\UpgradeWizards\SeminarAttachmentsToFalUpgradeWizard
 */
final class SeminarAttachmentsToFalUpgradeWizardTest extends FunctionalTestCase
{
    use FalHelper;

    protected $testExtensionsToLoad = [
        'typo3conf/ext/feuserextrafields',
        'typo3conf/ext/oelib',
        'typo3conf/ext/seminars',
    ];

    /**
     * @var SeminarAttachmentsToFalUpgradeWizard
     */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provideAdminBackEndUserForFal();
        $this->subject = new SeminarAttachmentsToFalUpgradeWizard();
    }

    /**
     * @test
     */
    public function isRegistered(): void
    {
        self::assertSame(
            SeminarAttachmentsToFalUpgradeWizard::class,
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['seminars_migrateSeminarAttachmentsToFal']
        );
    }

    /**
     * @test
     */
    public function canCheckForUpdateNecessary(): void
    {
        self::assertIsBool($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function canBeRun(): void
    {
        self::assertIsBool($this->subject->executeUpdate());
    }
}
