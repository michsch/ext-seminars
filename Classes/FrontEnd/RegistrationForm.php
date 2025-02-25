<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\FrontEnd;

use OliverKlee\Oelib\Authentication\FrontEndLoginManager;
use OliverKlee\Oelib\Configuration\ConfigurationRegistry;
use OliverKlee\Oelib\DataStructures\Collection;
use OliverKlee\Oelib\Exception\NotFoundException;
use OliverKlee\Oelib\Http\HeaderProxyFactory;
use OliverKlee\Oelib\Mapper\MapperRegistry;
use OliverKlee\Oelib\Session\Session;
use OliverKlee\Seminars\Mapper\EventMapper;
use OliverKlee\Seminars\Mapper\FrontEndUserGroupMapper;
use OliverKlee\Seminars\Mapper\FrontEndUserMapper;
use OliverKlee\Seminars\Mapper\RegistrationMapper;
use OliverKlee\Seminars\Model\Event;
use OliverKlee\Seminars\Model\FrontEndUser;
use OliverKlee\Seminars\Model\FrontEndUserGroup;
use OliverKlee\Seminars\Model\Registration;
use OliverKlee\Seminars\OldModel\LegacyEvent;
use OliverKlee\Seminars\OldModel\LegacyRegistration;
use OliverKlee\Seminars\Service\RegistrationManager;
use SJBR\StaticInfoTables\PiBaseApi;
use SJBR\StaticInfoTables\Utility\LocalizationUtility;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * This class is a controller which allows to create registrations on the FE.
 *
 * @deprecated #1545 will be removed in seminars 5.0
 */
class RegistrationForm extends AbstractEditor
{
    /**
     * the same as the class name
     *
     * @var string
     */
    public $prefixId = 'tx_seminars_registration_editor';

    /**
     * the names of the form fields to show (with the keys being the same as the values for performance reasons)
     *
     * @var string[]
     */
    private $formFieldsToShow = [];

    /**
     * the number of the current page of the form (starting with 0 for the first page)
     *
     * @var int
     */
    public $currentPageNumber = 0;

    /**
     * the keys of fields that are part of the billing address
     *
     * @var string[]
     */
    private const BILLING_ADDRESS_FIELDS = [
        'gender',
        'name',
        'company',
        'email',
        'address',
        'zip',
        'city',
        'country',
        'telephone',
    ];

    /**
     * @var PiBaseApi
     */
    private $staticInfo;

    /**
     * @var LegacyEvent seminar object
     */
    private $seminar;

    /**
     * @var LegacyRegistration|null
     */
    protected $registration;

    /**
     * @var string[]
     */
    private const REGISTRATION_FIELDS_ON_CONFIRMATION_PAGE = [
        'price',
        'seats',
        'total_price',
        'method_of_payment',
        'account_number',
        'bank_code',
        'bank_name',
        'account_owner',
        'attendees_names',
        'lodgings',
        'accommodation',
        'foods',
        'food',
        'checkboxes',
        'interests',
        'expectations',
        'background_knowledge',
        'known_from',
        'notes',
    ];

    /**
     * Overwrite this in an XClass with the keys of additional keys that should always be displayed.
     *
     * @var string[]
     */
    protected $alwaysEnabledFormFields = [];

    /**
     * The constructor.
     *
     * This class may only be instantiated after is has already been made sure
     * that the logged-in user is allowed to register for the corresponding
     * event (or edit a registration).
     *
     * Please note that it is necessary to call setAction() and setSeminar()
     * directly after instantiation.
     *
     * @param array $configuration TypoScript configuration for the plugin
     * @param ContentObjectRenderer $contentObjectRenderer the parent cObj content, needed for the flexforms
     */
    public function __construct(array $configuration, ContentObjectRenderer $contentObjectRenderer)
    {
        parent::__construct($configuration, $contentObjectRenderer);

        /** @var array<int, non-empty-string> $fieldKeys */
        $fieldKeys = GeneralUtility::trimExplode(
            ',',
            $this->getConfValueString('showRegistrationFields', 's_template_special'),
            true
        );
        foreach ($fieldKeys as $fieldKey) {
            $this->formFieldsToShow[$fieldKey] = $fieldKey;
        }
    }

    /**
     * @param string $action action for which to create the form, must be either "register" or "unregister",
     *        must not be empty
     */
    public function setAction(string $action): void
    {
        $this->initializeAction($action);
    }

    /**
     * Sets the seminar for which to create the form.
     *
     * @param LegacyEvent $event the event for which to create the form
     */
    public function setSeminar(LegacyEvent $event): void
    {
        $this->seminar = $event;
    }

    /**
     * Returns the configured seminar object.
     *
     * @return LegacyEvent the seminar instance
     *
     * @throws \BadMethodCallException if no seminar has been set yet
     */
    public function getSeminar(): LegacyEvent
    {
        if ($this->seminar === null) {
            throw new \BadMethodCallException(
                'Please set a proper seminar object via $this->setSeminar().',
                1333293187
            );
        }

        return $this->seminar;
    }

    /**
     * Returns the event for this registration form.
     */
    public function getEvent(): Event
    {
        return MapperRegistry::get(EventMapper::class)->find($this->getSeminar()->getUid());
    }

    /**
     * Sets the registration for which to create the unregistration form.
     *
     * @param LegacyRegistration $registration the registration to use
     */
    public function setRegistration(LegacyRegistration $registration): void
    {
        $this->registration = $registration;
    }

    /**
     * Returns the current registration object.
     */
    private function getRegistration(): ?LegacyRegistration
    {
        return $this->registration;
    }

    /**
     * Sets the form configuration to use.
     *
     * @param string $action action to perform, may be either "register" or "unregister", must not be empty
     */
    protected function initializeAction(string $action = 'register'): void
    {
        switch ($action) {
            case 'unregister':
                $formConfiguration = (array)$this->conf['form.']['unregistration.'];
                break;
            case 'register':
                // The fall-through is intended.
            default:
                // The current page number will be 1 if a 3-click registration
                // is configured and the first page was submitted successfully.
                // It will be 2 for a 3-click registration and the second page
                // submitted successfully. It will also be 2 for a 2-click
                // registration and the first page submitted successfully.
                // Note that to display the second page, this function is called
                // two times in a row if the current page number is higher than
                // zero. It is only the second page, that can process the
                // registration.
                if (($this->currentPageNumber == 1) || ($this->currentPageNumber == 2)) {
                    $formConfiguration = (array)$this->conf['form.']['registration.']['step2.'];
                } else {
                    $formConfiguration = (array)$this->conf['form.']['registration.']['step1.'];
                }
        }

        $this->setFormConfiguration($formConfiguration);
    }

    /**
     * Creates the HTML output.
     *
     * @return string HTML of the create/edit form
     */
    public function render(): string
    {
        $rawForm = parent::render();
        // For the confirmation page, we need to reload the whole thing. Yet,
        // the previous rendering still is necessary for processing the data.
        if ($this->currentPageNumber > 0) {
            $this->discardRenderedForm();
            $this->initializeAction();
            // This will produce a new form to which no data can be provided.
            $rawForm = $this->makeFormCreator()->render();
        }

        // Remove empty label tags that have been created due to a bug in FORMidable.
        $rawForm = preg_replace('/<label[^>]*><\\/label>/', '', $rawForm);
        $this->processTemplate($rawForm);
        $this->setLabels();
        $this->hideUnusedFormFields();

        if (!$this->getConfValueBoolean('createAdditionalAttendeesAsFrontEndUsers', 's_registration')) {
            $this->hideSubparts('attendees_position_and_email');
        }

        $this->setMarker('feuser_data', $this->getAllFeUserData());
        $this->setMarker('billing_address', $this->getBillingAddress());
        $this->setMarker('registration_data', $this->getAllRegistrationDataForConfirmation());
        $this->setMarker(
            'themselves_default_value',
            (int)$this->getConfValueString('registerThemselvesByDefaultForHiddenCheckbox')
        );

        return $this->getSubpart();
    }

    /**
     * Discards the rendered FORMIdable form from the page, including any header data.
     */
    private function discardRenderedForm(): void
    {
        $frontEndController = $this->getFrontEndController();
        // A mayday would be returned without unsetting the form ID.
        unset(
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ameos_formidable']['context']
            ['forms']['tx_seminars_pi1_registration_editor']
        );
        if (!\is_array($frontEndController->additionalHeaderData)) {
            return;
        }

        foreach ($frontEndController->additionalHeaderData as $key => $content) {
            if (\strpos((string)$content, 'FORMIDABLE:') !== false) {
                unset($frontEndController->additionalHeaderData[$key]);
            }
        }
    }

    /**
     * Selects the confirmation page (the second step of the registration form) for display. This affects $this->render().
     *
     * This method is used only in the unit tests.
     *
     * @param array $parameters the entered form data with the field names as array keys (including the submit button)
     */
    public function setPage(array $parameters): void
    {
        $this->currentPageNumber = $parameters['next_page'];
    }

    /**
     * Checks whether we are on the last page of the registration form and we can proceed to saving the registration.
     *
     * @return bool TRUE if we can proceed to saving the registration, FALSE otherwise
     */
    public function isLastPage(): bool
    {
        return $this->currentPageNumber == 2;
    }

    /**
     * Processes the entered/edited registration and stores it in the DB.
     *
     * In addition, the entered payment data is stored in the FE user session.
     *
     * @param array $parameters the entered form data with the field names as array keys (including the submit button)
     */
    public function processRegistration(array $parameters): void
    {
        $this->saveDataToSession($parameters);
        $registrationManager = $this->getRegistrationManager();
        if (!$registrationManager->canCreateRegistration($this->getSeminar(), $parameters)) {
            return;
        }

        $newRegistration = $registrationManager->createRegistration($this->getSeminar(), $parameters, $this);
        if ($this->getConfValueBoolean('createAdditionalAttendeesAsFrontEndUsers', 's_registration')) {
            $this->createAdditionalAttendees($newRegistration);
        }

        $registrationManager->sendEmailsForNewRegistration($this);
    }

    /**
     * Creates additional attendees as FE users and adds them to the provided registration.
     */
    protected function createAdditionalAttendees(Registration $registration): void
    {
        $allPersonsData = $this->getAdditionalRegisteredPersonsData();
        if (empty($allPersonsData)) {
            return;
        }

        $userMapper = MapperRegistry::get(FrontEndUserMapper::class);
        $pageUid = $this->getConfValueInteger('sysFolderForAdditionalAttendeeUsersPID', 's_registration');

        $userGroupMapper = MapperRegistry::get(FrontEndUserGroupMapper::class);
        /** @var Collection<FrontEndUserGroup> $userGroups */
        $userGroups = new Collection();
        $userGroupUids = GeneralUtility::intExplode(
            ',',
            $this->getConfValueString('userGroupUidsForAdditionalAttendeesFrontEndUsers', 's_registration'),
            true
        );
        foreach ($userGroupUids as $uid) {
            $userGroup = $userGroupMapper->find($uid);
            $userGroups->add($userGroup);
        }

        $random = GeneralUtility::makeInstance(Random::class);
        $additionalPersons = $registration->getAdditionalPersons();
        /** @var array $personData */
        foreach ($allPersonsData as $personData) {
            $user = GeneralUtility::makeInstance(FrontEndUser::class);
            $user->setPageUid($pageUid);
            $user->setPassword($random->generateRandomHexString(8));
            $eMailAddress = $personData[3];
            $user->setEmailAddress($eMailAddress);

            $isUnique = false;
            $suffixCounter = 0;
            do {
                $userName = $eMailAddress;
                if ($suffixCounter > 0) {
                    $userName .= '-' . $suffixCounter;
                }
                try {
                    $userMapper->findByUserName($userName);
                } catch (NotFoundException $exception) {
                    $isUnique = true;
                }

                $suffixCounter++;
            } while (!$isUnique);

            $user->setUserName($userName);
            $user->setUserGroups($userGroups);

            $user->setFirstName($personData[0]);
            $user->setLastName($personData[1]);
            $user->setName($personData[0] . ' ' . $personData[1]);
            $user->setJobTitle($personData[2]);

            $additionalPersons->add($user);
        }

        $registrationMapper = MapperRegistry::get(RegistrationMapper::class);
        $registrationMapper->save($registration);
    }

    /**
     * Checks whether there are at least the number of seats provided in $formData['value'] available.
     *
     * @param array $formData associative array with the element "value" in which the number of seats to check for
     *        is stored
     *
     * @return bool TRUE if there are at least $formData['value'] seats available, FALSE otherwise
     */
    public function canRegisterSeats(array $formData): bool
    {
        return $this->getRegistrationManager()->canRegisterSeats($this->getSeminar(), (int)$formData['value']);
    }

    /**
     * Checks whether a checkbox is checked OR the "finish registration" button has not just been clicked.
     *
     * @param array $formData associative array with the element "value" in which the current value
     *        of the checkbox (0 or 1) is stored
     *
     * @return bool TRUE if the checkbox is checked or we are not on the confirmation page, FALSE otherwise
     */
    public function isTermsChecked(array $formData): bool
    {
        return (bool)$formData['value'] || ($this->currentPageNumber != 2);
    }

    /**
     * Checks whether the "travelling terms" checkbox (ie. the second "terms" checkbox) is enabled in the event record
     * *and* via TS setup.
     */
    public function isTerms2Enabled(): bool
    {
        return $this->hasRegistrationFormField(['elementname' => 'terms_2']) && $this->getSeminar()->hasTerms2();
    }

    /**
     * Checks whether the "terms_2" checkbox is checked (if it is enabled in the
     * configuration). If the checkbox is disabled in the configuration, this
     * function always returns TRUE. It also always returns TRUE if the
     * "finish registration" button hasn't just been clicked.
     *
     * @param array $formData associative array with the element "value" in which the current value of the checkbox
     *        (0 or 1) is stored
     */
    public function isTerms2CheckedAndEnabled(array $formData): bool
    {
        return (bool)$formData['value'] || !$this->isTerms2Enabled() || ($this->currentPageNumber != 2);
    }

    /**
     * Checks whether a method of payment is selected OR this event has no
     * payment methods set at all OR the corresponding registration field is
     * not visible in the registration form (in which case it is neither
     * necessary nor possible to select any payment method) OR this event has
     * no price at all.
     *
     * @param array $formData associative array with the element "value" in which the currently selected value
     *        (a positive integer or NULL if no radiobutton is selected) is stored
     */
    public function isMethodOfPaymentSelected(array $formData): bool
    {
        return $this->isRadioButtonSelected($formData['value']) || !$this->getSeminar()->hasPaymentMethods()
            || !$this->getSeminar()->hasAnyPrice() || !$this->showMethodsOfPayment();
    }

    /**
     * Checks whether a radio button in a radio button group is selected.
     *
     * @param mixed $radioGroupValue the currently selected value (a positive integer) or NULL if no button is selected
     *
     * @return bool TRUE if a radio button is selected, FALSE if none is selected
     */
    private function isRadioButtonSelected($radioGroupValue): bool
    {
        return (bool)$radioGroupValue;
    }

    /**
     * Checks whether a form field should be displayed (and evaluated) at all.
     * This is specified via TS setup (or flexforms) using the
     * "showRegistrationFields" variable.
     *
     * @param array $parameters the contents of the "params" child of the userobj node as key/value pairs
     *        (used for retrieving the current form field name)
     */
    public function hasRegistrationFormField(array $parameters): bool
    {
        return isset($this->formFieldsToShow[$parameters['elementname']]);
    }

    /**
     * Checks whether a form field should be displayed (and evaluated) at all.
     * This is specified via TS setup (or flexforms) using the
     * "showRegistrationFields" variable.
     *
     * In addition, this function takes into account whether the form field
     * actually has any meaningful content.
     * Example: The payment methods field will be disabled if the current event
     * does not have any payment methods.
     *
     * After some refactoring, this function will replace the function hasRegistrationFormField.
     *
     * @param string $key the key of the field to test, must not be empty
     *
     * @return bool TRUE if the current form field should be displayed, FALSE otherwise
     */
    public function isFormFieldEnabled(string $key): bool
    {
        $isFormFieldAlwaysEnabled = in_array($key, $this->alwaysEnabledFormFields, true);
        if ($isFormFieldAlwaysEnabled) {
            return true;
        }

        // Some containers cannot be enabled or disabled via TS setup, but
        // are containers and depend on their content being displayed.
        switch ($key) {
            case 'payment':
                $result = $this->isFormFieldEnabled('price')
                    || $this->isFormFieldEnabled('method_of_payment')
                    || $this->isFormFieldEnabled('banking_data');
                break;
            case 'banking_data':
                $result = $this->isFormFieldEnabled('account_number')
                    || $this->isFormFieldEnabled('account_owner')
                    || $this->isFormFieldEnabled('bank_code')
                    || $this->isFormFieldEnabled('bank_name');
                break;
            case 'billing_address':
                // This fields actually can also be disabled via TS setup.
                $result = isset($this->formFieldsToShow[$key])
                    && (
                        $this->isFormFieldEnabled('company')
                        || $this->isFormFieldEnabled('gender')
                        || $this->isFormFieldEnabled('name')
                        || $this->isFormFieldEnabled('address')
                        || $this->isFormFieldEnabled('zip')
                        || $this->isFormFieldEnabled('city')
                        || $this->isFormFieldEnabled('country')
                        || $this->isFormFieldEnabled('telephone')
                        || $this->isFormFieldEnabled('email')
                    );
                break;
            case 'more_seats':
                $result = $this->isFormFieldEnabled('seats')
                    || $this->isFormFieldEnabled('attendees_names')
                    || $this->isFormFieldEnabled('kids');
                break;
            case 'lodging_and_food':
                $result = $this->isFormFieldEnabled('lodgings')
                    || $this->isFormFieldEnabled('accommodation')
                    || $this->isFormFieldEnabled('foods')
                    || $this->isFormFieldEnabled('food');
                break;
            case 'additional_information':
                $result = $this->isFormFieldEnabled('checkboxes')
                    || $this->isFormFieldEnabled('interests')
                    || $this->isFormFieldEnabled('expectations')
                    || $this->isFormFieldEnabled('background_knowledge')
                    || $this->isFormFieldEnabled('known_from')
                    || $this->isFormFieldEnabled('notes');
                break;
            case 'entered_data':
                $result = $this->isFormFieldEnabled('feuser_data')
                    || $this->isFormFieldEnabled('billing_address')
                    || $this->isFormFieldEnabled('registration_data');
                break;
            case 'all_terms':
                $result = $this->isFormFieldEnabled('terms')
                    || $this->isFormFieldEnabled('terms_2');
                break;
            case 'traveling_terms':
                // "traveling_terms" is an alias for "terms_2" which we use to
                // avoid the problem that subpart names need to be prefix-free.
                $result = $this->isFormFieldEnabled('terms_2');
                break;
            case 'billing_data':
                // "billing_data" is an alias for "billing_address" which we use
                // to prevent two subparts from having the same name.
                $result = $this->isFormFieldEnabled('billing_address');
                break;
            default:
                $result = isset($this->formFieldsToShow[$key]);
        }

        // Some fields depend on the availability of their data.
        switch ($key) {
            case 'method_of_payment':
                $result = $result && $this->showMethodsOfPayment();
                break;
            case 'account_number':
                // The fallthrough is intended.
            case 'bank_code':
                // The fallthrough is intended.
            case 'bank_name':
                // The fallthrough is intended.
            case 'account_owner':
                $result = $result && $this->getSeminar()->hasAnyPrice();
                break;
            case 'lodgings':
                $result = $result && $this->hasLodgings();
                break;
            case 'foods':
                $result = $result && $this->hasFoods();
                break;
            case 'checkboxes':
                $result = $result && $this->hasCheckboxes();
                break;
            case 'terms_2':
                $result = $result && $this->isTerms2Enabled();
                break;
            default:
            // nothing to do
            }

        return $result;
    }

    /**
     * Checks whether a form field should be displayed (and evaluated) at all.
     * This is specified via TS setup (or flexforms) using the
     * "showRegistrationFields" variable.
     *
     * This function also checks if the current event has a price set at all,
     * and returns only TRUE if the event has a price (i.e., is not completely for
     * free) and the current form field should be displayed.
     *
     * @deprecated #1571 will be removed in seminars 5.0
     *
     * @param array $parameters the contents of the "params" child of the userobj node as key/value pairs
     *        (used for retrieving the current form field name)
     *
     * @return bool TRUE if the current form field should be displayed AND the current event is not completely for free,
     *              FALSE otherwise
     */
    public function hasBankDataFormField(array $parameters): bool
    {
        return $this->hasRegistrationFormField($parameters) && $this->getSeminar()->hasAnyPrice();
    }

    /**
     * Gets the URL of the page that should be displayed after a user has signed up for an event,
     * but only if the form has been submitted from stage 2 (the confirmation page).
     *
     * If the current FE user account is a one-time account and
     * checkLogOutOneTimeAccountsAfterRegistration is enabled in the TS setup,
     * the FE user will be automatically logged out.
     *
     * @return string complete URL of the FE page with a message
     */
    public function getThankYouAfterRegistrationUrl(): string
    {
        if (
            $this->getConfValueBoolean('logOutOneTimeAccountsAfterRegistration')
            && Session::getInstance(Session::TYPE_USER)->getAsBoolean('onetimeaccount')
        ) {
            // @deprecated #1947 will be removed in seminars 5.0
            $this->getFrontEndController()->fe_user->logoff();
        }

        $pageUid = $this->getConfValueInteger('thankYouAfterRegistrationPID', 's_registration');
        $sendParameters = $this->getConfValueBoolean(
            'sendParametersToThankYouAfterRegistrationPageUrl',
            's_registration'
        );

        return $this->createUrlForRedirection($pageUid, $sendParameters);
    }

    /**
     * Gets the URL of the page that should be displayed after a user has unregistered from an event.
     *
     * @return string complete URL of the FE page with a message
     */
    public function getPageToShowAfterUnregistrationUrl(): string
    {
        $pageUid = $this->getConfValueInteger('pageToShowAfterUnregistrationPID', 's_registration');
        $sendParameters = $this->getConfValueBoolean(
            'sendParametersToPageToShowAfterUnregistrationUrl',
            's_registration'
        );

        return $this->createUrlForRedirection($pageUid, $sendParameters);
    }

    /**
     * Creates a URL for redirection.
     *
     * @param bool $sendParameters whether GET parameters should be added to the URL
     *
     * @return string complete URL of the FE page with a message
     */
    private function createUrlForRedirection(int $pageUid, bool $sendParameters = true): string
    {
        // On freshly updated sites, the configuration value might not be set yet.
        // To avoid breaking the site, we use the event list in this case.
        if ($pageUid === 0) {
            $pageUid = $this->getConfValueInteger('listPID');
        }

        $linkConfiguration = ['parameter' => $pageUid];
        if ($sendParameters) {
            $linkConfiguration['additionalParams'] = GeneralUtility::implodeArrayForUrl(
                'tx_seminars_pi1',
                ['showUid' => $this->getSeminar()->getUid()],
                '',
                false,
                true
            );
        }

        return GeneralUtility::locationHeaderUrl($this->cObj->typoLink_URL($linkConfiguration));
    }

    /**
     * Provides data items for the list of available payment methods.
     *
     * @return array[] items from the payment methods table as an array
     *               with the keys "caption" (for the title) and "value" (for the uid)
     */
    public function populateListPaymentMethods(): array
    {
        if (!$this->getSeminar()->hasPaymentMethods()) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_seminars_payment_methods');
        $rows = $queryBuilder
            ->select('uid', 'title')
            ->from('tx_seminars_payment_methods')
            ->join(
                'tx_seminars_payment_methods',
                'tx_seminars_seminars_payment_methods_mm',
                'mm',
                $queryBuilder->expr()->eq(
                    'mm.uid_foreign',
                    $queryBuilder->quoteIdentifier('tx_seminars_payment_methods.uid')
                )
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'mm.uid_local',
                    $queryBuilder->createNamedParameter($this->getSeminar()->getTopicOrSelfUid(), \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'caption' => $row['title'],
                'value' => $row['uid'],
            ];
        }

        return $result;
    }

    /**
     * Creates the data for the seats drop-down.
     *
     * @return int[][] array with sub arrays: [caption => i, value => i]
     */
    public function populateSeats(): array
    {
        $result = [];

        $event = $this->getEvent();
        $maximumBookableSeats = $this->getConfValueInteger('maximumBookableSeats');
        if ($event->hasMaximumAttendees() && $event->hasVacancies()) {
            $numberOfVacancies = $event->getVacancies();
            $availableSeatsForBooking = min($numberOfVacancies, $maximumBookableSeats);
        } else {
            $availableSeatsForBooking = $maximumBookableSeats;
        }

        for ($i = 1; $i <= $availableSeatsForBooking; $i++) {
            $result[] = ['caption' => $i, 'value' => $i];
        }

        return $result;
    }

    /**
     * Checks whether the methods of payment should be displayed at all,
     * i.e., whether they are enable in the setup and the current event actually
     * has any payment methods assigned and has at least one price.
     *
     * @return bool TRUE if the payment methods should be displayed, FALSE otherwise
     */
    public function showMethodsOfPayment(): bool
    {
        $event = $this->getSeminar();
        return $event->hasPaymentMethods()
            && $this->getSeminar()->hasAnyPrice()
            && $this->hasRegistrationFormField(['elementname' => 'method_of_payment']);
    }

    /**
     * Gets the currently logged-in FE user's data nicely formatted as HTML so that it can be directly included on the
     * confirmation page.
     *
     * The telephone number and the e-mail address will have labels in front of them.
     *
     * @return string the currently logged-in FE user's data
     */
    public function getAllFeUserData(): string
    {
        /** @var mixed[] $userData */
        $userData = $this->getFrontEndController()->fe_user->user;

        /** @var array<int, non-empty-string> $fieldKeys */
        $fieldKeys = GeneralUtility::trimExplode(',', $this->getConfValueString('showFeUserFieldsInRegistrationForm'));
        /** @var array<int, non-empty-string> $fieldsWithLabels */
        $fieldsWithLabels = GeneralUtility::trimExplode(
            ',',
            $this->getConfValueString('showFeUserFieldsInRegistrationFormWithLabel'),
            true
        );

        foreach ($fieldKeys as $key) {
            $hasLabel = in_array($key, $fieldsWithLabels, true);
            $fieldValue = isset($userData[$key]) ? \htmlspecialchars(
                (string)$userData[$key],
                ENT_QUOTES | ENT_HTML5
            ) : '';
            $wrappedFieldValue = '<span id="tx-seminars-feuser-field-' . $key . '">' . $fieldValue . '</span>';
            if ($fieldValue !== '') {
                $marker = $hasLabel ? ($this->translate('label_' . $key) . ' ') : '';
                $marker .= $wrappedFieldValue;
            } else {
                $marker = '';
            }

            $this->setMarker('user_' . $key, $marker);
        }

        $rawOutput = $this->getSubpart('REGISTRATION_CONFIRMATION_FEUSER');
        $outputWithoutEmptyMarkers = preg_replace('/###USER_[A-Z]+###/', '', $rawOutput);
        $outputWithoutBlankLines = preg_replace('/^\\s*<br[^>]*>\\s*$/m', '', $outputWithoutEmptyMarkers);

        return $outputWithoutBlankLines;
    }

    /**
     * Gets the already entered registration data nicely formatted as HTML so
     * that it can be directly included on the confirmation page.
     *
     * @return string the entered registration data, nicely formatted as HTML
     */
    public function getAllRegistrationDataForConfirmation(): string
    {
        $result = '';

        foreach ($this->getAllFieldKeysForConfirmationPage() as $key) {
            if ($this->isFormFieldEnabled($key)) {
                $result .= $this->getFormDataItemAndLabelForConfirmation($key);
            }
        }

        return $result;
    }

    /**
     * Formats one data item from the form as HTML, including a heading.
     * If the entered data is empty, an empty string will be returned (so the heading will only be included for
     * non-empty data).
     *
     * @param string $key the key of the field for which the data should be displayed
     *
     * @return string the data from the corresponding form field formatted in HTML with a heading (or an empty string if
     *         the form data is empty)
     */
    protected function getFormDataItemAndLabelForConfirmation(string $key): string
    {
        $currentFormData = $this->getFormDataItemForConfirmationPage($key);
        if ($currentFormData === '') {
            return '';
        }

        $this->setMarker('registration_data_heading', $this->createLabelForRegistrationElementOnConfirmationPage($key));

        $fieldContent = str_replace("\r", '<br />', \htmlspecialchars($currentFormData, ENT_QUOTES | ENT_HTML5));
        $this->setMarker('registration_data_body', $fieldContent);

        return $this->getSubpart('REGISTRATION_CONFIRMATION_DATA');
    }

    /**
     * Creates the label text for an element on the confirmation page.
     */
    protected function createLabelForRegistrationElementOnConfirmationPage(string $key): string
    {
        return rtrim($this->translate('label_' . $key), ':');
    }

    /**
     * Returns all the keys of all registration fields for the confirmation page.
     *
     * @return string[]
     */
    protected function getAllFieldKeysForConfirmationPage(): array
    {
        return self::REGISTRATION_FIELDS_ON_CONFIRMATION_PAGE;
    }

    /**
     * Retrieves (and converts, if necessary) the form data item with the key $key.
     *
     * @param string $key the key of the field for which the data should be retrieved
     *
     * @return string the formatted data item, will not be htmlspecialchared yet, might be empty
     *
     * @throws \InvalidArgumentException
     */
    protected function getFormDataItemForConfirmationPage(string $key): string
    {
        if (!in_array($key, $this->getAllFieldKeysForConfirmationPage(), true)) {
            throw new \InvalidArgumentException(
                'The form data item ' . $key . ' is not valid on the confirmation page. Valid items are: ' .
                implode(', ', $this->getAllFieldKeysForConfirmationPage()),
                1389813109
            );
        }

        // The "total_price" field doesn't exist as an actual renderlet and so cannot be read.
        $currentFormData = ($key !== 'total_price') ? $this->getFormValue($key) : '';

        switch ($key) {
            case 'price':
                $currentFormData = $this->getSelectedPrice();
                break;
            case 'total_price':
                $currentFormData = $this->getTotalPriceWithUnit();
                break;
            case 'method_of_payment':
                $currentFormData = $this->getSelectedPaymentMethod();
                break;
            case 'lodgings':
                $this->ensureArray($currentFormData);
                $currentFormData = $this->getCaptionsForSelectedOptions(
                    $this->getSeminar()->getLodgings(),
                    $currentFormData
                );
                break;
            case 'foods':
                $this->ensureArray($currentFormData);
                $currentFormData = $this->getCaptionsForSelectedOptions(
                    $this->getSeminar()->getFoods(),
                    $currentFormData
                );
                break;
            case 'checkboxes':
                $this->ensureArray($currentFormData);
                $currentFormData = $this->getCaptionsForSelectedOptions(
                    $this->getSeminar()->getCheckboxes(),
                    $currentFormData
                );
                break;
            case 'attendees_names':
                if (
                    $this->isFormFieldEnabled('registered_themselves')
                    && $this->getFormValue('registered_themselves') == '1'
                ) {
                    $userUid = FrontEndLoginManager::getInstance()->getLoggedInUserUid();
                    $user = MapperRegistry::get(FrontEndUserMapper::class)->find($userUid);
                    $userData = [$user->getName()];
                    if ($this->getConfValueBoolean('createAdditionalAttendeesAsFrontEndUsers', 's_registration')) {
                        if ($user->hasJobTitle()) {
                            $userData[] = $user->getJobTitle();
                        }
                        if ($user->hasEmailAddress()) {
                            $userData[] = $user->getEmailAddress();
                        }
                    }

                    $currentFormData = implode(', ', $userData) . "\r" . $currentFormData;
                }
                break;
            default:
            // nothing to do
            }

        return (string)$currentFormData;
    }

    /**
     * Ensures that the parameter is an array. If it is no array yet, it will be changed to an empty array.
     *
     * @param mixed $data variable that should be ensured to be an array
     */
    protected function ensureArray(&$data): void
    {
        if (!is_array($data)) {
            $data = [];
        }
    }

    /**
     * Retrieves the selected price, completely with caption (for example: "Standard price") and currency.
     *
     * If no price has been selected, the first available price will be used.
     *
     * @return string the selected price with caption and unit
     */
    private function getSelectedPrice(): string
    {
        $availablePrices = $this->getSeminar()->getAvailablePrices();

        return $availablePrices[$this->getKeyOfSelectedPrice()]['caption'];
    }

    /**
     * Retrieves the key of the selected price.
     *
     * If no price has been selected, the first available price will be used.
     *
     * @return string the key of the selected price, will always be a valid key
     */
    private function getKeyOfSelectedPrice(): string
    {
        $availablePrices = $this->getSeminar()->getAvailablePrices();
        $selectedPrice = $this->getFormValue('price');

        // If no (available) price is selected, use the first price by default.
        if (!$this->getSeminar()->isPriceAvailable($selectedPrice)) {
            $selectedPrice = key($availablePrices);
        }

        return $selectedPrice;
    }

    /**
     * Takes the selected price and the selected number of seats and calculates
     * the total price. The total price will be returned with the currency unit appended.
     *
     * @return string the total price calculated from the form data including the currency unit, e.g., "240.00 EUR"
     */
    private function getTotalPriceWithUnit(): string
    {
        $result = '';

        $seats = (int)$this->getFormValue('seats');

        // Only show the total price if the seats selector is displayed
        // (otherwise the total price will be same as the price anyway).
        if ($seats > 0) {
            // Build the total price for this registration and add it to the form
            // data to show it on the confirmation page.
            // This value will not be saved to the database from here. It will be
            // calculated again when creating the registration object.
            // It will not be added if no total price can be calculated (e.g.
            // total price = 0.00)
            $availablePrices = $this->getSeminar()->getAvailablePrices();
            $selectedPrice = $this->getKeyOfSelectedPrice();

            if ($availablePrices[$selectedPrice]['amount'] !== '0.00') {
                $totalAmount = $seats * (float)$availablePrices[$selectedPrice]['amount'];
                $result = $this->getSeminar()->formatPrice((string)$totalAmount);
            }
        }

        return $result;
    }

    /**
     * Gets the caption of the selected payment method. If no valid payment
     * method has been selected, this function returns an empty string.
     *
     * @return string the caption of the selected payment method or an empty
     *                string if no valid payment method has been selected
     */
    private function getSelectedPaymentMethod(): string
    {
        $result = '';
        foreach ($this->populateListPaymentMethods() as $paymentMethod) {
            if ($paymentMethod['value'] == $this->getFormValue('method_of_payment')) {
                $result = $paymentMethod['caption'];
                break;
            }
        }

        // We use strip_tags to remove any trailing <br /> tags.
        return strip_tags($result);
    }

    /**
     * Takes the selected options for a list of options and displays it
     * nicely using their captions, separated by a carriage return (ASCII 13).
     *
     * @param array[] $availableOptions all available options for this form element as a nested array, the outer array
     *        having the UIDs of the options as keys, the inner array having the keys "caption" (for the visible
     *        captions) and "value" (the UID again), may be empty
     * @param int[] $selectedOptions the selected options with the array values being the UIDs of the corresponding
     *        options, may be empty
     *
     * @return string the captions of the selected options, separated by CR
     */
    private function getCaptionsForSelectedOptions(array $availableOptions, array $selectedOptions): string
    {
        $result = '';

        if (!empty($selectedOptions)) {
            $captions = [];

            foreach ($selectedOptions as $currentSelection) {
                if (isset($availableOptions[$currentSelection])) {
                    $captions[] = $availableOptions[$currentSelection]['caption'];
                }
                $result = \implode("\r", $captions);
            }
        }

        return $result;
    }

    /**
     * Gets the already entered billing address nicely formatted as HTML so
     * that it can be directly included on the confirmation page.
     *
     * @return string the already entered registration data, nicely formatted as HTML
     */
    public function getBillingAddress(): string
    {
        $result = '';

        foreach (self::BILLING_ADDRESS_FIELDS as $key) {
            $currentFormData = $this->getFormValue($key);
            if ($currentFormData !== '') {
                $label = $this->translate('label_' . $key);
                $wrappedLabel = '<span class="tx-seminars-billing-data-label">' . $label . '</span>';

                // If the gender field is hidden, it would have an empty value,
                // so we wouldn't be here. So let's convert the "gender" index
                // into a readable string.
                if ($key === 'gender') {
                    $currentFormData = $this->translate('label_gender.I.' . (int)$currentFormData);
                }
                $processedFormData = str_replace(
                    "\r",
                    '<br />',
                    \htmlspecialchars($currentFormData, ENT_QUOTES | ENT_HTML5)
                );
                $wrappedFormData = '<span class="tx-seminars-billing-data-item tx-seminars-billing-data-item-' . $key . '">' .
                    $processedFormData . '</span>';

                $result .= $wrappedLabel . ' ' . $wrappedFormData . "<br />\n";
            }
        }

        $this->setMarker('registration_billing_address', $result);

        return $this->getSubpart('REGISTRATION_CONFIRMATION_BILLING');
    }

    /**
     * Checks whether the current field is non-empty if the payment method
     * "bank transfer" is selected. If a different payment method is selected
     * (or none is defined as "bank transfer"), the check is always positive and
     * returns TRUE.
     *
     * @deprecated #1571 will be removed in seminars 5.0
     *
     * @param array $formData associative array with the element "value" in which the value of the current field is provided
     *
     * @return bool TRUE if the field is non-empty or "bank transfer" is not selected
     */
    public function hasBankData(array $formData): bool
    {
        $result = true;

        if (empty($formData['value'])) {
            $bankTransferUid = $this->getConfValueInteger('bankTransferUID');

            $paymentMethod = (int)$this->getFormValue('method_of_payment');

            if (($bankTransferUid > 0) && ($paymentMethod == $bankTransferUid)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Returns a data item of the currently logged-in FE user or, if that data
     * has additionally been stored in the FE user session (as billing address),
     * the data from the session.
     *
     * This function may only be called when a FE user is logged in.
     *
     * The caller needs to take care of htmlspecialcharing the data.
     *
     * @param array $params contents of the "params" XML child of the userobj node (needs to contain an element with
     *        the key "key")
     *
     * @return string the contents of the element
     */
    public function getFeUserData(array $params): string
    {
        $result = $this->retrieveDataFromSession($params);
        if (!empty($result)) {
            return $result;
        }

        $key = $params['key'];
        $feUserData = $this->getFrontEndController()->fe_user->user;
        $result = (string)$feUserData[$key];

        // If the country is empty, try the static info country instead.
        if (empty($result) && ($key === 'country')) {
            $staticInfoCountry = $feUserData['static_info_country'];
            if (empty($staticInfoCountry)) {
                $result = $this->getDefaultCountry();
            } else {
                $this->initStaticInfo();
                $result = (string)$this->staticInfo->getStaticInfoName('COUNTRIES', $staticInfoCountry, '', '', true);
            }
        }

        return $result;
    }

    /**
     * Provides a localized list of country names from static_tables.
     *
     * @return array[] localized country names from static_tables as an
     *               array with the keys "caption" (for the title) and "value"
     *               (in this case, the same as the caption)
     */
    public function populateListCountries(): array
    {
        $this->initStaticInfo();
        /** @var string[] $allCountries */
        $allCountries = $this->staticInfo->initCountries('ALL', '', true);

        $result = [];
        // Puts an empty item at the top so we won't have Afghanistan (the first entry) pre-selected for empty values.
        $result[] = ['caption' => '', 'value' => ''];

        foreach ($allCountries as $currentCountryName) {
            $result[] = [
                'caption' => $currentCountryName,
                'value' => $currentCountryName,
            ];
        }

        return $result;
    }

    /**
     * Returns the default country as localized string.
     *
     * @return string the default country's localized name, will be empty if there is no default country
     */
    private function getDefaultCountry(): string
    {
        $defaultCountryCode = ConfigurationRegistry::get('plugin.tx_staticinfotables_pi1')->getAsString('countryCode');
        if ($defaultCountryCode === '') {
            return '';
        }

        $this->initStaticInfo();

        $currentLanguageCode = ConfigurationRegistry::get('config')->getAsString('language');
        $identifiers = ['iso' => $defaultCountryCode];
        $result = LocalizationUtility::getLabelFieldValue($identifiers, 'static_countries', $currentLanguageCode, true);

        return $result;
    }

    /**
     * Provides data items for the list of option checkboxes for this event.
     *
     * @return array[] items from the checkboxes table as an array
     *                 with the keys "caption" (for the title) and "value" (for the uid)
     */
    public function populateCheckboxes(): array
    {
        return $this->getSeminar()->hasCheckboxes() ? $this->getSeminar()->getCheckboxes() : [];
    }

    /**
     * Checks whether our current event has any option checkboxes AND the
     * checkboxes should be displayed at all.
     *
     * @return bool TRUE if we have a non-empty list of checkboxes AND this list should be displayed, FALSE otherwise
     */
    public function hasCheckboxes(): bool
    {
        return $this->getSeminar()->hasCheckboxes() && $this->hasRegistrationFormField(['elementname' => 'checkboxes']);
    }

    /**
     * Provides data items for the list of lodging options for this event.
     *
     * @return array[] items from the lodgings table as an array with the keys "caption" (for the title) and "value" (for the uid)
     */
    public function populateLodgings(): array
    {
        $result = [];

        if ($this->getSeminar()->hasLodgings()) {
            $result = $this->getSeminar()->getLodgings();
        }

        return $result;
    }

    /**
     * Checks whether at least one lodging option is selected (if there is at
     * least one lodging option for this event and the lodging options should
     * be displayed).
     *
     * @param array $formData the value of the current field in an associative array witch the element "value"
     *
     * @return bool TRUE if at least one item is selected or no lodging options can be selected
     */
    public function isLodgingSelected(array $formData): bool
    {
        return !empty($formData['value']) || !$this->hasLodgings();
    }

    /**
     * Checks whether our current event has any lodging options and the
     * lodging options should be displayed at all.
     *
     * @return bool TRUE if we have a non-empty list of lodging options and this list should be displayed, FALSE otherwise
     */
    public function hasLodgings(): bool
    {
        return $this->getSeminar()->hasLodgings() && $this->hasRegistrationFormField(['elementname' => 'lodgings']);
    }

    /**
     * Provides data items for the list of food options for this event.
     *
     * @return array[] items from the foods table as an array with the keys "caption" (for the title) and "value"
     *         (for the uid)
     */
    public function populateFoods(): array
    {
        $result = [];

        if ($this->getSeminar()->hasFoods()) {
            $result = $this->getSeminar()->getFoods();
        }

        return $result;
    }

    /**
     * Checks whether our current event has any food options and the food
     * options should be displayed at all.
     *
     * @return bool TRUE if we have a non-empty list of food options and this list should be displayed, FALSE otherwise
     */
    public function hasFoods(): bool
    {
        return $this->getSeminar()->hasFoods() && $this->hasRegistrationFormField(['elementname' => 'foods']);
    }

    /**
     * Checks whether at least one food option is selected (if there is at
     * least one food option for this event and the food options should
     * be displayed).
     *
     * @param array $formData associative array with the element "value" in which the value of the current field is
     *        provided
     *
     * @return bool TRUE if at least one item is selected or no food options can be selected
     */
    public function isFoodSelected(array $formData): bool
    {
        return !empty($formData['value']) || !$this->hasFoods();
    }

    /**
     * @throws \BadMethodCallException if this method is called without a logged-in FE user
     */
    protected function getLoggedInUser(): FrontEndUser
    {
        $userUid = FrontEndLoginManager::getInstance()->getLoggedInUserUid();
        $user = $userUid > 0 ? MapperRegistry::get(FrontEndUserMapper::class)->find($userUid) : null;
        if (!$user instanceof FrontEndUser) {
            throw new \BadMethodCallException('No user logged in.', 1633436053);
        }

        return $user;
    }

    /**
     * Provides data items for the prices for this event.
     *
     * @return string[][] available prices as an array with the keys "caption" (for the title) and "value"
     */
    public function populatePrices(): array
    {
        return $this->getRegistrationManager()->getPricesAvailableForUser(
            $this->getSeminar(),
            $this->getLoggedInUser()
        );
    }

    /**
     * Checks whether a valid price is selected or the "price" registration
     * field is not visible in the registration form (in which case it is not
     * possible to select a price).
     *
     * @param array $formData associative array with the element "value" in which the currently selected value
     *        (a positive integer) or NULL if no radiobutton is selected is provided
     *
     * @return bool true if a valid price is selected or the price field
     *                 is hidden, false if none is selected, but could have been selected
     */
    public function isValidPriceSelected(array $formData): bool
    {
        return $this->getSeminar()->isPriceAvailable($formData['value'])
            || !$this->hasRegistrationFormField(['elementname' => 'price']);
    }

    /**
     * Returns the UID of the preselected payment method.
     *
     * This will be:
     * a) the same payment method as previously selected (within the current
     * session) if that method is available for the current event
     * b) if only one payment method is available, that payment method
     * c) 0 in all other cases
     *
     * @return int the UID of the preselected payment method or 0 if should will be preselected
     */
    public function getPreselectedPaymentMethod(): int
    {
        $availablePaymentMethods = $this->populateListPaymentMethods();
        if (count($availablePaymentMethods) === 1) {
            return (int)$availablePaymentMethods[0]['value'];
        }

        $result = 0;
        $paymentMethodFromSession = $this->retrieveSavedMethodOfPayment();

        foreach ($availablePaymentMethods as $paymentMethod) {
            if ((int)$paymentMethod['value'] === $paymentMethodFromSession) {
                $result = (int)$paymentMethod['value'];
                break;
            }
        }

        return $result;
    }

    /**
     * Saves the following data to the FE user session:
     * - payment method
     * - account number
     * - bank code
     * - bank name
     * - account_owner
     * - gender
     * - name
     * - address
     * - zip
     * - city
     * - country
     * - telephone
     * - email
     *
     * @param array $parameters the form data (may be empty)
     */
    private function saveDataToSession(array $parameters): void
    {
        if (empty($parameters)) {
            return;
        }

        $parametersToSave = [
            'method_of_payment',
            'account_number',
            'bank_code',
            'bank_name',
            'account_owner',
            'company',
            'gender',
            'name',
            'address',
            'zip',
            'city',
            'country',
            'telephone',
            'email',
            'registered_themselves',
        ];

        foreach ($parametersToSave as $currentKey) {
            if (isset($parameters[$currentKey])) {
                Session::getInstance(Session::TYPE_USER)
                    ->setAsString($this->prefixId . '_' . $currentKey, $parameters[$currentKey]);
            }
        }
    }

    /**
     * Retrieves the saved payment method from the FE user session.
     *
     * @return int the UID of the payment method that has been saved in the FE user session or 0 if there is none
     */
    private function retrieveSavedMethodOfPayment(): int
    {
        return (int)$this->retrieveDataFromSession(['key' => 'method_of_payment']);
    }

    /**
     * Retrieves the data for a given key from the FE user session. Returns an
     * empty string if no data for that key is stored.
     *
     * @param array $parameters the contents of the "params" child of the userobj node as key/value pairs
     *        (used for retrieving the current form field name)
     *
     * @return string the data stored in the FE user session under the given key, might be empty
     */
    public function retrieveDataFromSession(array $parameters): string
    {
        return Session::getInstance(Session::TYPE_USER)->getAsString($this->prefixId . '_' . $parameters['key']);
    }

    /**
     * Gets the prefill value for the account owner: If it is provided, the
     * account owner from a previous registration in the same FE user session, or the FE user's name.
     *
     * @return string a name to prefill the account owner
     */
    public function prefillAccountOwner(): string
    {
        $result = $this->retrieveDataFromSession(['key' => 'account_owner']);

        if (empty($result)) {
            $result = $this->getFeUserData(['key' => 'name']);
        }

        return $result;
    }

    /**
     * Creates and initializes $this->staticInfo (if that hasn't been done yet).
     */
    private function initStaticInfo(): void
    {
        if ($this->staticInfo === null) {
            $this->staticInfo = GeneralUtility::makeInstance(PiBaseApi::class);
            $this->staticInfo->init();
        }
    }

    /**
     * Hides form fields that are either disabled via TS setup or that have
     * nothing to select (e.g. if there are no payment methods) from the templating process.
     */
    private function hideUnusedFormFields(): void
    {
        static $availableFormFields = [
            'step_counter',
            'payment',
            'price',
            'method_of_payment',
            'banking_data',
            'account_number',
            'bank_code',
            'bank_name',
            'account_owner',
            'billing_address',
            'billing_data',
            'company',
            'gender',
            'name',
            'address',
            'zip',
            'city',
            'country',
            'telephone',
            'email',
            'additional_information',
            'interests',
            'expectations',
            'background_knowledge',
            'lodging_and_food',
            'accommodation',
            'food',
            'known_from',
            'more_seats',
            'seats',
            'registered_themselves',
            'attendees_names',
            'kids',
            'lodgings',
            'foods',
            'checkboxes',
            'notes',
            'entered_data',
            'feuser_data',
            'registration_data',
            'all_terms',
            'terms',
            'terms_2',
            'traveling_terms',
        ];

        $formFieldsToHide = [];

        foreach ($availableFormFields as $key) {
            if (!$this->isFormFieldEnabled($key)) {
                $formFieldsToHide[$key] = $key;
            }
        }

        $numberOfClicks = $this->getConfValueInteger('numberOfClicksForRegistration', 's_registration');

        // If we first visit the registration form, the value of
        // $this->currentPageNumber is 0.
        // If we had an error in our form input and we were send back to the
        // registration form, $this->currentPageNumber is 2.
        if (($this->currentPageNumber == 0) || ($this->currentPageNumber == 2)) {
            switch ($numberOfClicks) {
                case 2:
                    $formFieldsToHide['button_continue'] = 'button_continue';
                    break;
                case 3:
                    // The fall-through is intended.
                default:
                    $formFieldsToHide['button_submit'] = 'button_submit';
            }
        }

        $this->hideSubparts(implode(',', $formFieldsToHide), 'registration_wrapper');
    }

    /**
     * Provides a string "Registration form: step x of y" for the current page.
     * The number of the first and last page can be configured via TS setup.
     *
     * @return string a localized string displaying the number of the current and the last page
     */
    public function getStepCounter(): string
    {
        $lastPageNumberForDisplay = $this->getConfValueInteger('numberOfLastRegistrationPage');
        $currentPageNumber = $this->getConfValueInteger('numberOfFirstRegistrationPage') + $this->currentPageNumber;

        // Decreases $lastPageNumberForDisplay by one if we only have 2 clicks to registration.
        $numberOfClicks = $this->getConfValueInteger('numberOfClicksForRegistration', 's_registration');

        if ($numberOfClicks === 2) {
            $lastPageNumberForDisplay--;
        }

        $currentPageNumberForDisplay = min($lastPageNumberForDisplay, $currentPageNumber);

        return sprintf($this->translate('label_step_counter'), $currentPageNumberForDisplay, $lastPageNumberForDisplay);
    }

    /**
     * Processes the registration that should be removed.
     */
    public function processUnregistration(): void
    {
        /** @var \formidable_mainrenderlet $cancelButtonRenderlet */
        $cancelButtonRenderlet = $this->getFormCreator()->aORenderlets['button_cancel'];
        if ($cancelButtonRenderlet->hasThrown('click')) {
            $redirectUrl = GeneralUtility::locationHeaderUrl(
                $this->pi_getPageLink(
                    $this->getConfValueInteger(
                        'myEventsPID'
                    )
                )
            );
            HeaderProxyFactory::getInstance()->getHeaderProxy()->addHeader('Location:' . $redirectUrl);
            exit;
        }

        $this->getRegistrationManager()->removeRegistration($this->getRegistration()->getUid(), $this);
    }

    /**
     * Returns the data of the additional registered persons.
     *
     * The inner array will have the following format:
     * 0 => first name
     * 1 => last name
     * 2 => job title
     * 3 => e-mail address
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}> the entered person's data, might be empty
     */
    public function getAdditionalRegisteredPersonsData(): array
    {
        $jsonEncodedData = $this->getFormValue('structured_attendees_names');
        if (!is_string($jsonEncodedData) || $jsonEncodedData === '') {
            return [];
        }

        $result = \json_decode($jsonEncodedData, true);
        if (!is_array($result)) {
            $result = [];
        }

        return $result;
    }

    /**
     * Gets the number of entered persons in the form by counting the lines
     * in the "additional attendees names" field and the state of the "register myself" checkbox.
     *
     * @return int the number of entered persons, will be >= 0
     */
    public function getNumberOfEnteredPersons(): int
    {
        if ($this->isFormFieldEnabled('registered_themselves')) {
            $formData = (int)$this->getFormValue('registered_themselves');
            $themselves = ($formData > 0) ? 1 : 0;
        } else {
            $themselves = $this->getConfValueInteger('registerThemselvesByDefaultForHiddenCheckbox');
        }

        return $themselves + count($this->getAdditionalRegisteredPersonsData());
    }

    /**
     * Checks whether the number of selected seats matches the number of
     * registered persons (including the FE user themselves as well as the additional attendees).
     *
     * @return bool whether the number of seats matches the number of registered persons
     */
    public function validateNumberOfRegisteredPersons(): bool
    {
        if ((int)$this->getFormValue('seats') <= 0) {
            return false;
        }
        if (!$this->isFormFieldEnabled('attendees_names')) {
            return true;
        }

        return (int)$this->getFormValue('seats') === $this->getNumberOfEnteredPersons();
    }

    /**
     * Validates the e-mail addresses of additional persons for non-emptiness and validity.
     *
     * If the entering of additional persons as FE user records is disabled, this function will always return TRUE.
     *
     * @return bool
     *         TRUE if either additional persons as FE users are disabled or all entered e-mail addresses are non-empty and valid,
     *         FALSE otherwise
     */
    public function validateAdditionalPersonsEmailAddresses(): bool
    {
        if (!$this->isFormFieldEnabled('attendees_names')) {
            return true;
        }
        if (!$this->getConfValueBoolean('createAdditionalAttendeesAsFrontEndUsers', 's_registration')) {
            return true;
        }

        $isValid = true;
        foreach ($this->getAdditionalRegisteredPersonsData() as $onePersonData) {
            if (!isset($onePersonData[3]) || !GeneralUtility::validEmail($onePersonData[3])) {
                $isValid = false;
                break;
            }
        }

        return $isValid;
    }

    /**
     * Gets the error message to return if the number of registered persons
     * does not match the number of seats.
     *
     * @return string the localized error message, will be empty if both numbers match
     */
    public function getMessageForSeatsNotMatchingRegisteredPersons(): string
    {
        $seats = (int)$this->getFormValue('seats');
        $persons = $this->getNumberOfEnteredPersons();

        if ($persons < $seats) {
            $result = $this->translate('message_lessAttendeesThanSeats');
        } elseif ($persons > $seats) {
            $result = $this->translate('message_moreAttendeesThanSeats');
        } else {
            $result = '';
        }

        return $result;
    }

    private function getRegistrationManager(): RegistrationManager
    {
        return RegistrationManager::getInstance();
    }
}
