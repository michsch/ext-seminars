<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\Mapper;

use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Test case.
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
final class EventMapperTest extends FunctionalTestCase
{
    /**
     * @var string[]
     */
    protected $testExtensionsToLoad = ['typo3conf/ext/oelib', 'typo3conf/ext/seminars'];

    /**
     * @var \Tx_Seminars_Mapper_Event
     */
    private $subject = null;

    protected function setUp()
    {
        parent::setUp();

        $this->subject = new \Tx_Seminars_Mapper_Event();
    }

    /**
     * @return void
     */
    private function assertContainsModelWithUid(\Tx_Oelib_List $models, int $uid)
    {
        self::assertTrue($models->hasUid($uid));
    }

    /**
     * @return void
     */
    private function assertNotContainsModelWithUid(\Tx_Oelib_List $models, int $uid)
    {
        self::assertFalse($models->hasUid($uid));
    }

    /**
     * @test
     */
    public function findWithUidReturnsEventInstance()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->find(1);

        self::assertInstanceOf(\Tx_Seminars_Model_Event::class, $result);
    }

    /**
     * @test
     */
    public function findWithUidOfExistingRecordReturnsRecordAsModel()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        /** @var \Tx_Seminars_Model_Event $result */
        $result = $this->subject->find(1);

        self::assertSame('a complete event', $result->getTitle());
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresEventWithoutRegistrationsWithoutDigestDate()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertNotContainsModelWithUid($result, 1);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresEventWithoutRegistrationsWithDigestDateInPast()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertNotContainsModelWithUid($result, 2);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsEventWithRegistrationAndWithoutDigestDate()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertContainsModelWithUid($result, 3);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailSortsEventsByBeginDateInAscendingOrder()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();
        $this->assertContainsModelWithUid($result, 3);
        $this->assertContainsModelWithUid($result, 4);

        $uids = GeneralUtility::intExplode(',', $result->getUids(), true);
        $indexOfLaterEvent = \array_search(3, $uids, true);
        $indexOfEarlierEvent = \array_search(4, $uids, true);

        self::assertTrue($indexOfEarlierEvent < $indexOfLaterEvent);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsEventWithRegistrationAfterDigestDate()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertContainsModelWithUid($result, 4);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresEventWithRegistrationOnlyBeforeDigestDate()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertNotContainsModelWithUid($result, 5);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsEventWithRegistrationsBeforeAndAfterDigestDate()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertContainsModelWithUid($result, 8);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailFindsDateWithRegistrationAfterDigestDate()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertContainsModelWithUid($result, 9);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresTopicWithRegistrationAfterDigestDate()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertNotContainsModelWithUid($result, 10);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresHiddenEvent()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertNotContainsModelWithUid($result, 11);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresDeletedEvent()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertNotContainsModelWithUid($result, 7);
    }

    /**
     * @test
     */
    public function findForRegistrationDigestEmailIgnoresDeletedRegistration()
    {
        $this->importDataSet(__DIR__ . '/Fixtures/Events.xml');

        $result = $this->subject->findForRegistrationDigestEmail();

        $this->assertNotContainsModelWithUid($result, 6);
    }
}