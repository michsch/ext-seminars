<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyUnit\BackEnd;

use OliverKlee\Seminars\Tests\LegacyUnit\BackEnd\Fixtures\DummyModule;
use OliverKlee\Seminars\Tests\LegacyUnit\Support\Traits\BackEndTestsTrait;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Information\Typo3Version;

final class AbstractModuleTest extends TestCase
{
    use BackEndTestsTrait;

    /**
     * @var DummyModule
     */
    private $subject;

    protected function setUp(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 11) {
            self::markTestSkipped('Skipping because this code will be removed before adding 11LTS compatibility.');
        }

        $this->unifyTestingEnvironment();
        $this->subject = new DummyModule();
    }

    protected function tearDown(): void
    {
        $this->restoreOriginalEnvironment();
    }

    /**
     * @test
     */
    public function getPageDataInitiallyReturnsEmptyArray(): void
    {
        self::assertSame([], $this->subject->getPageData());
    }

    /**
     * @test
     */
    public function getPageDataReturnsCompleteDataSetViaSetPageData(): void
    {
        $this->subject->setPageData(['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $this->subject->getPageData());
    }
}
