<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\Functional\BackEnd;

use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Seminars\BackEnd\AbstractModule;
use OliverKlee\Seminars\BackEnd\Controller;
use OliverKlee\Seminars\Csv\CsvDownloader;
use OliverKlee\Seminars\Tests\Functional\Traits\LanguageHelper;
use Prophecy\Prophecy\ProphecySubjectInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @covers \OliverKlee\Seminars\BackEnd\Controller
 */
final class ControllerTest extends FunctionalTestCase
{
    use LanguageHelper;

    /**
     * @var array<int, string>
     */
    protected $testExtensionsToLoad = ['typo3conf/ext/oelib', 'typo3conf/ext/seminars'];

    /**
     * @var Controller
     */
    private $subject = null;

    /**
     * @var DummyConfiguration
     */
    private $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBackendUserFromFixture(1);
        $this->initializeBackEndLanguage();

        $this->configuration = new DummyConfiguration(
            [
                'filenameForEventsCsv' => 'events.csv',
                'filenameForRegistrationsCsv' => 'registrations.csv',
            ]
        );
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', $this->configuration);

        $this->subject = new Controller();
    }

    protected function tearDown(): void
    {
        // Manually purge the TYPO3 FIFO queue
        GeneralUtility::makeInstance(CsvDownloader::class);
        ConfigurationRegistry::purgeInstance();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function isAbstractModule(): void
    {
        self::assertInstanceOf(AbstractModule::class, $this->subject);
    }

    /**
     * @test
     */
    public function mainActionWithCsvFlagReturnsCsvDownload(): void
    {
        $csvBody = 'foo;bar';
        $exporterProphecy = $this->prophesize(CsvDownloader::class);
        // @phpstan-ignore-next-line PHPStan does not know Prophecy (at least not without the corresponding plugin).
        $exporterProphecy->main()->shouldBeCalled()->willReturn($csvBody);
        /** @var CsvDownloader&ProphecySubjectInterface $xsvExporter */
        $xsvExporter = $exporterProphecy->reveal();
        GeneralUtility::addInstance(CsvDownloader::class, $xsvExporter);

        $GLOBALS['_GET']['csv'] = '1';

        $response = $this->subject->mainAction();

        self::assertSame($csvBody, (string)$response->getBody());
    }

    /**
     * @test
     */
    public function mainActionWithCsvFlagForEventTableUsesEventCsvFilename(): void
    {
        $csvBody = 'foo;bar';
        $exporterProphecy = $this->prophesize(CsvDownloader::class);
        // @phpstan-ignore-next-line PHPStan does not know Prophecy (at least not without the corresponding plugin).
        $exporterProphecy->main()->shouldBeCalled()->willReturn($csvBody);
        /** @var CsvDownloader&ProphecySubjectInterface $xsvExporter */
        $xsvExporter = $exporterProphecy->reveal();
        GeneralUtility::addInstance(CsvDownloader::class, $xsvExporter);

        $GLOBALS['_GET']['csv'] = '1';
        $GLOBALS['_GET']['table'] = 'tx_seminars_seminars';

        $response = $this->subject->mainAction();

        $filename = $this->configuration->getAsString('filenameForEventsCsv');
        $contentDispositionHeader = $response->getHeader('Content-Disposition')[0];
        self::assertContains('; filename=' . $filename, $contentDispositionHeader);
    }

    /**
     * @test
     */
    public function mainActionWithCsvFlagForRegistrationsTableUsesEventCsvFilename(): void
    {
        $csvBody = 'foo;bar';
        $exporterProphecy = $this->prophesize(CsvDownloader::class);
        // @phpstan-ignore-next-line PHPStan does not know Prophecy (at least not without the corresponding plugin).
        $exporterProphecy->main()->shouldBeCalled()->willReturn($csvBody);
        /** @var CsvDownloader&ProphecySubjectInterface $xsvExporter */
        $xsvExporter = $exporterProphecy->reveal();
        GeneralUtility::addInstance(CsvDownloader::class, $xsvExporter);

        $GLOBALS['_GET']['csv'] = '1';
        $GLOBALS['_GET']['table'] = 'tx_seminars_attendances';

        $response = $this->subject->mainAction();

        $filename = $this->configuration->getAsString('filenameForRegistrationsCsv');
        $contentDispositionHeader = $response->getHeader('Content-Disposition')[0];
        self::assertContains('; filename=' . $filename, $contentDispositionHeader);
    }
}