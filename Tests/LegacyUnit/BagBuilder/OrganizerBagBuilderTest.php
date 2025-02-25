<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyUnit\BagBuilder;

use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Bag\AbstractBag;
use OliverKlee\Seminars\BagBuilder\OrganizerBagBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OliverKlee\Seminars\BagBuilder\OrganizerBagBuilder
 */
final class OrganizerBagBuilderTest extends TestCase
{
    /**
     * @var OrganizerBagBuilder
     */
    private $subject;

    /**
     * @var TestingFramework
     */
    private $testingFramework;

    protected function setUp(): void
    {
        $this->testingFramework = new TestingFramework('tx_seminars');

        $this->subject = new OrganizerBagBuilder();
        $this->subject->setTestMode();
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUp();
    }

    ///////////////////////////////////////////
    // Tests for the basic builder functions.
    ///////////////////////////////////////////

    /**
     * @test
     */
    public function builderBuildsABag(): void
    {
        self::assertInstanceOf(AbstractBag::class, $this->subject->build());
    }

    /////////////////////////////
    // Tests for limitToEvent()
    /////////////////////////////

    /**
     * @test
     */
    public function limitToEventWithNegativeEventUidThrowsException(): void
    {
        $this->expectException(
            \InvalidArgumentException::class
        );
        $this->expectExceptionMessage(
            'The parameter $eventUid must be > 0.'
        );

        $this->subject->limitToEvent(-1);
    }

    /**
     * @test
     */
    public function limitToEventWithZeroEventUidThrowsException(): void
    {
        $this->expectException(
            \InvalidArgumentException::class
        );
        $this->expectExceptionMessage(
            'The parameter $eventUid must be > 0.'
        );

        $this->subject->limitToEvent(0);
    }

    /**
     * @test
     */
    public function limitToEventFindsOneOrganizerOfEvent(): void
    {
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers'
        );
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 1]
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid
        );

        $this->subject->limitToEvent($eventUid);
        $bag = $this->subject->build();

        self::assertEquals(
            1,
            $bag->countWithoutLimit()
        );
    }

    /**
     * @test
     */
    public function limitToEventFindsTwoOrganizersOfEvent(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 2]
        );
        $organizerUid1 = $this->testingFramework->createRecord(
            'tx_seminars_organizers'
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid1
        );
        $organizerUid2 = $this->testingFramework->createRecord(
            'tx_seminars_organizers'
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid2
        );

        $this->subject->limitToEvent($eventUid);
        $bag = $this->subject->build();

        self::assertEquals(
            2,
            $bag->countWithoutLimit()
        );
    }

    /**
     * @test
     */
    public function limitToEventIgnoresOrganizerOfOtherEvent(): void
    {
        $eventUid1 = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 1]
        );
        $organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers'
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid1,
            $organizerUid
        );
        $eventUid2 = $this->testingFramework->createRecord(
            'tx_seminars_seminars'
        );

        $this->subject->limitToEvent($eventUid2);
        $bag = $this->subject->build();

        self::assertTrue(
            $bag->isEmpty()
        );
    }

    /**
     * @test
     */
    public function limitToEventSortsByRelationSorting(): void
    {
        $eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            ['organizers' => 2]
        );
        $organizerUid1 = $this->testingFramework->createRecord(
            'tx_seminars_organizers'
        );
        $organizerUid2 = $this->testingFramework->createRecord(
            'tx_seminars_organizers'
        );

        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid2
        );
        $this->testingFramework->createRelation(
            'tx_seminars_seminars_organizers_mm',
            $eventUid,
            $organizerUid1
        );

        $this->subject->limitToEvent($eventUid);
        $bag = $this->subject->build();
        $bag->rewind();

        self::assertEquals(
            $organizerUid2,
            $bag->current()->getUid()
        );
    }
}
