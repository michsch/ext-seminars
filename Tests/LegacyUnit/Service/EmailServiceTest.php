<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyUnit\Service;

use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\Configuration\DummyConfiguration;
use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Testing\CacheNullifyer;
use OliverKlee\Oelib\Testing\TestingFramework;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\Model\Organizer;
use OliverKlee\Seminars\Model\Registration;
use OliverKlee\Seminars\Service\EmailService;
use OliverKlee\Seminars\Tests\Unit\Traits\EmailTrait;
use OliverKlee\Seminars\Tests\Unit\Traits\MakeInstanceTrait;
use OliverKlee\Seminars\ViewHelpers\DateRangeViewHelper;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * @covers \OliverKlee\Seminars\Service\EmailService
 */
final class EmailServiceTest extends TestCase
{
    use EmailTrait;
    use MakeInstanceTrait;

    /**
     * @var string
     */
    private const DATE_FORMAT_YMD = '%d.%m.%Y';

    /**
     * @var EmailService
     */
    private $subject;

    /**
     * @var TestingFramework
     */
    private $testingFramework;

    /**
     * @var Event
     */
    private $event;

    /**
     * @var FrontEndUser
     */
    private $user;

    /**
     * @var Organizer
     */
    private $organizer;

    /**
     * @var LanguageService
     */
    private $languageBackup;

    protected function setUp(): void
    {
        (new CacheNullifyer())->setAllCoreCaches();

        Bootstrap::initializeBackendAuthentication();
        $this->languageBackup = $GLOBALS['LANG'] ?? null;
        if ((new Typo3Version())->getMajorVersion() >= 10) {
            $languageService = LanguageService::create('default');
        } else {
            // @phpstan-ignore-next-line This line is for TYPO3 9LTS only, and we currently are on 10LTS.
            $languageService = new LanguageService();
            $languageService->init('default');
        }
        $GLOBALS['LANG'] = $languageService;

        $this->testingFramework = new TestingFramework('tx_seminars');

        $configuration = new DummyConfiguration(['dateFormatYMD' => self::DATE_FORMAT_YMD]);
        ConfigurationRegistry::getInstance()->set('plugin.tx_seminars', $configuration);

        $this->email = $this->createEmailMock();

        $this->organizer = new Organizer();
        $this->organizer->setData(
            [
                'title' => 'Brain Gourmets',
                'email' => 'organizer@example.com',
                'email_footer' => 'Best workshops in town!',
            ]
        );
        $organizers = new Collection();
        $organizers->add($this->organizer);

        $this->event = new Event();
        $this->event->setData(
            [
                'title' => 'A nice event',
                'begin_date' => mktime(10, 0, 0, 4, 2, 2016),
                'end_date' => mktime(18, 30, 0, 4, 3, 2016),
                'registrations' => new Collection(),
                'organizers' => $organizers,
            ]
        );

        $this->user = new FrontEndUser();
        $this->user->setData(['name' => 'John Doe', 'email' => 'john.doe@example.com']);
        $registration = new Registration();
        $registration->setData([]);
        $registration->setFrontEndUser($this->user);
        $this->event->attachRegistration($registration);

        $this->subject = new EmailService();
    }

    protected function tearDown(): void
    {
        $this->testingFramework->cleanUp();
        $GLOBALS['LANG'] = $this->languageBackup;
    }

    /**
     * @test
     */
    public function classIsSingleton(): void
    {
        self::assertInstanceOf(SingletonInterface::class, $this->subject);
    }

    // Tests for sendEmailToAttendees

    /**
     * @test
     */
    public function sendEmailToAttendeesForEventWithoutRegistrationsNotSendsMail(): void
    {
        /** @var Collection<Registration> $registrations */
        $registrations = new Collection();
        $this->event->setRegistrations($registrations);

        $this->email->expects(self::exactly(0))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesUsesTypo3DefaultFromAddressAsSender(): void
    {
        $defaultMailFromAddress = 'system-foo@example.com';
        $defaultMailFromName = 'Mr. Default';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = $defaultMailFromAddress;
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = $defaultMailFromName;

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');

        self::assertArrayHasKey($defaultMailFromAddress, $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesUsesFirstOrganizerAsReplyTo(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'system-foo@example.com';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Mr. Default';

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');

        self::assertArrayHasKey($this->organizer->getEmailAddress(), $this->getReplyToOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesWithoutTypo3DefaultFromAddressUsesFirstOrganizerAsSender(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = '';

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');

        self::assertArrayHasKey($this->organizer->getEmailAddress(), $this->getFromOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesSendsEmailWithProvidedSubject(): void
    {
        $subject = 'Bonjour!';

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, $subject, 'Hello!');

        self::assertSame(
            $subject,
            $this->email->getSubject()
        );
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesReplacesEventTitleInSubject(): void
    {
        $subjectPrefix = 'Event title goes here: ';

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, $subjectPrefix . '%eventTitle', 'Hello!');

        self::assertSame(
            $subjectPrefix . $this->event->getTitle(),
            $this->email->getSubject()
        );
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesReplacesEventDateInSubject(): void
    {
        $subjectPrefix = 'Event date goes here: ';

        $formattedDate = (new DateRangeViewHelper())->render($this->event, '-');

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, $subjectPrefix . '%eventDate', 'Hello!');

        self::assertSame(
            $subjectPrefix . $formattedDate,
            $this->email->getSubject()
        );
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesSendsEmailWithProvidedBody(): void
    {
        $body = 'Life is good.';

        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', $body);

        self::assertStringContainsString($body, $this->getTextBodyOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesSendsToFirstAttendee(): void
    {
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');

        self::assertSame(
            [$this->user->getEmailAddress() => $this->user->getName()],
            $this->getToOfEmail($this->email)
        );
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForTwoRegistrationsSendsTwoEmails(): void
    {
        $secondUser = new FrontEndUser();
        $secondUser->setData(['email' => 'jane@example.com', 'name' => 'Jane Doe']);
        $secondRegistration = new Registration();
        $secondRegistration->setData([]);
        $secondRegistration->setFrontEndUser($secondUser);
        $this->event->attachRegistration($secondRegistration);

        $this->email
            ->expects(self::exactly(2))
            ->method('send')
            ->willReturn(true);
        $this->addMockedInstance(MailMessage::class, $this->email);
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForRegistrationWithoutUserNotSendsMail(): void
    {
        $registration = new Registration();
        $registration->setData([]);
        /** @var Collection<Registration> $registrations */
        $registrations = new Collection();
        $registrations->add($registration);
        $this->event->setRegistrations($registrations);

        $this->email->expects(self::exactly(0))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForAttendeeWithoutEmailAddressNotSendsMail(): void
    {
        $this->user->setEmailAddress('');

        $this->email->expects(self::exactly(0))->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesInsertsSalutationIntoMailTextWithSalutationMarker(): void
    {
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', '%salutation (This was the salutation)');

        self::assertStringContainsString($this->user->getName(), $this->getTextBodyOfEmail($this->email));
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesInsertsUserNameIntoMailTextWithUserNameMarker(): void
    {
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello %userName!');

        self::assertStringContainsString(
            'Hello ' . $this->user->getName() . '!',
            $this->getTextBodyOfEmail($this->email)
        );
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesInsertsEventTitleIntoMailTextWithEventTitleMarker(): void
    {
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Event: %eventTitle');

        self::assertStringContainsString(
            'Event: ' . $this->event->getTitle(),
            $this->getTextBodyOfEmail($this->email)
        );
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesInsertsEventDateIntoMailTextWithEventDateMarker(): void
    {
        $formattedDate = (new DateRangeViewHelper())->render($this->event, '-');

        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Date: %eventDate');

        self::assertStringContainsString(
            'Date: ' . $formattedDate,
            $this->getTextBodyOfEmail($this->email)
        );
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForOrganizerWithoutFooterNotAppendsFooterSeparator(): void
    {
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->organizer->setEmailFooter('');

        $this->email->expects(self::once())->method('send');
        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');

        self::assertStringNotContainsString(
            '-- ',
            $this->getTextBodyOfEmail($this->email)
        );
    }

    /**
     * @test
     */
    public function sendEmailToAttendeesForOrganizerWithFooterAppendsFooter(): void
    {
        $this->email->expects(self::once())->method('send');
        $this->addMockedInstance(MailMessage::class, $this->email);

        $this->subject->sendEmailToAttendees($this->event, 'Bonjour!', 'Hello!');

        self::assertStringContainsString(
            "\n-- \n" . $this->organizer->getEmailFooter(),
            $this->getTextBodyOfEmail($this->email)
        );
    }
}
