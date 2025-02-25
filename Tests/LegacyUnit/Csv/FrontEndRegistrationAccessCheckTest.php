<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyUnit\Csv;

use OliverKlee\Oelib\Authentication\FrontEndLoginManager;
use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Seminars\Csv\FrontEndRegistrationAccessCheck;
use OliverKlee\Seminars\Csv\Interfaces\CsvAccessCheck;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use PHPUnit\Framework\TestCase;

final class FrontEndRegistrationAccessCheckTest extends TestCase
{
    /**
     * @var FrontEndRegistrationAccessCheck
     */
    private $subject;

    /**
     * @var DummyConfiguration
     */
    private $seminarsPluginConfiguration;

    /**
     * @var int
     */
    private $vipsGroupUid = 12431;

    protected function setUp(): void
    {
        $configurationRegistry = ConfigurationRegistry::getInstance();
        $configurationRegistry->set('plugin', new DummyConfiguration());

        $this->seminarsPluginConfiguration = new DummyConfiguration();
        $this->seminarsPluginConfiguration->setAsInteger('defaultEventVipsFeGroupID', $this->vipsGroupUid);
        $configurationRegistry->set('plugin.tx_seminars_pi1', $this->seminarsPluginConfiguration);

        $this->subject = new FrontEndRegistrationAccessCheck();
    }

    protected function tearDown(): void
    {
        ConfigurationRegistry::purgeInstance();
        FrontEndLoginManager::purgeInstance();
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
    public function hasAccessForNoFrontEndUserReturnsFalse(): void
    {
        FrontEndLoginManager::getInstance()->logInUser();

        $event = $this->createMock(LegacyEvent::class);
        $this->subject->setEvent($event);

        self::assertFalse(
            $this->subject->hasAccess()
        );
    }

    /**
     * @test
     */
    public function hasAccessForNonVipFrontEndUserAndNoVipAccessReturnsFalse(): void
    {
        $this->seminarsPluginConfiguration->setAsBoolean('allowCsvExportOfRegistrationsInMyVipEventsView', false);

        $user = $this->createMock(FrontEndUser::class);
        $userUid = 42;
        $user->method('getUid')->willReturn($userUid);
        FrontEndLoginManager::getInstance()->logInUser($user);

        $event = $this->createMock(LegacyEvent::class);
        $event->method('isUserVip')->with(
            $userUid,
            $this->vipsGroupUid
        )->willReturn(false);
        $this->subject->setEvent($event);

        self::assertFalse(
            $this->subject->hasAccess()
        );
    }

    /**
     * @test
     */
    public function hasAccessForVipFrontEndUserAndNoVipAccessReturnsFalse(): void
    {
        $this->seminarsPluginConfiguration->setAsBoolean('allowCsvExportOfRegistrationsInMyVipEventsView', false);

        $user = $this->createMock(FrontEndUser::class);
        $userUid = 42;
        $user->method('getUid')->willReturn($userUid);
        FrontEndLoginManager::getInstance()->logInUser($user);

        $event = $this->createMock(LegacyEvent::class);
        $event->method('isUserVip')->with(
            $userUid,
            $this->vipsGroupUid
        )->willReturn(true);
        $this->subject->setEvent($event);

        self::assertFalse(
            $this->subject->hasAccess()
        );
    }

    /**
     * @test
     */
    public function hasAccessForNonVipFrontEndUserAndVipAccessReturnsFalse(): void
    {
        $this->seminarsPluginConfiguration->setAsBoolean('allowCsvExportOfRegistrationsInMyVipEventsView', true);

        $user = $this->createMock(FrontEndUser::class);
        $userUid = 42;
        $user->method('getUid')->willReturn($userUid);
        FrontEndLoginManager::getInstance()->logInUser($user);

        $event = $this->createMock(LegacyEvent::class);
        $event->method('isUserVip')->with(
            $userUid,
            $this->vipsGroupUid
        )->willReturn(false);
        $this->subject->setEvent($event);

        self::assertFalse(
            $this->subject->hasAccess()
        );
    }

    /**
     * @test
     */
    public function hasAccessForVipFrontEndUserAndVipAccessReturnsTrue(): void
    {
        $this->seminarsPluginConfiguration->setAsBoolean('allowCsvExportOfRegistrationsInMyVipEventsView', true);

        $user = $this->createMock(FrontEndUser::class);
        $userUid = 42;
        $user->method('getUid')->willReturn($userUid);
        FrontEndLoginManager::getInstance()->logInUser($user);

        $event = $this->createMock(LegacyEvent::class);
        $event->method('isUserVip')->with($userUid, $this->vipsGroupUid)
            ->willReturn(true);
        $this->subject->setEvent($event);

        self::assertTrue(
            $this->subject->hasAccess()
        );
    }
}
