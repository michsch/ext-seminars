<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2007-2014 Oliver Klee (typo3-coding@oliverklee.de)
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

require_once(PATH_typo3 . 'template.php');
if (is_object($LANG)) {
	$LANG->includeLLFile(t3lib_extMgm::extPath('seminars') . 'locallang.xml');
}

require_once(t3lib_extMgm::extPath('seminars') . 'tx_seminars_modifiedSystemTables.php');

/**
 * Plugin "CSV export".
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 * @author Niels Pardon <mail@niels-pardon.de>
 * @author Bernd Schönbach <bernd@oliverklee.de>
 */
class tx_seminars_pi2 extends Tx_Oelib_TemplateHelper {
	/**
	 * @var integer
	 */
	const CSV_TYPE_NUMBER = 736;

	/**
	 * @var integer HTTP status code for "page not found"
	 */
	const NOT_FOUND = 404;

	/**
	 * @var integer HTTP status code for "access denied"
	 */
	const ACCESS_DENIED = 403;

	/**
	 * @var integer the depth of the recursion for the back-end pages
	 */
	const RECURSION_DEPTH = 250;

	/**
	 * @var string export mode for attachments created from back end
	 */
	const EXPORT_MODE_WEB = 'web';

	/**
	 * @var string export mode for attachments send via e-mail
	 */
	const EXPORT_MODE_EMAIL = 'e-mail';

	/**
	 * @var string same as class name
	 */
	public $prefixId = 'tx_seminars_pi2';
	/**
	 * @var string path to this script relative to the extension dir
	 */
	public $scriptRelPath = 'pi2/class.tx_seminars_pi2.php';

	/**
	 * @var string the extension key
	 */
	public $extKey = 'seminars';

	/**
	 * @var tx_seminars_configgetter This object provides access to configuration values in plugin.tx_seminars.
	 */
	private $configGetter = NULL;

	/**
	 * @var string the TYPO3 mode set for testing purposes
	 */
	private $typo3Mode = '';

	/**
	 * @var integer the HTTP status code of error
	 */
	private $errorType = 0;

	/**
	 * @var string the export mode for the CSV file possible values are
	 *             EXPORT_MODE_WEB and EXPORT_MODE_WEB
	 */
	private $exportMode = self::EXPORT_MODE_WEB;

	/**
	 * @var language the language object for translating the CSV headings
	 */
	private $language = NULL;

	/**
	 * The constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->loadLocallangFiles();
	}

	/**
	 * Frees as much memory that has been used by this object as possible.
	 */
	public function __destruct() {
		unset($this->configGetter, $this->language);

		parent::__destruct();
	}

	/**
	 * Creates a CSV export.
	 *
	 * @param string $unused (unused)
	 * @param array $configuration TypoScript configuration for the plugin, may be empty
	 *
	 * @return string HTML for the plugin, might be empty
	 */
	public function main($unused, array $configuration) {
		try {
			$this->init($configuration);

			switch ($this->piVars['table']) {
				case 'tx_seminars_seminars':
					$result = $this->createAndOutputListOfEvents(intval($this->piVars['pid']));
					break;
				case 'tx_seminars_attendances':
					$result = $this->createAndOutputListOfRegistrations(intval($this->piVars['eventUid']));
					break;
				default:
					$result = $this->addErrorHeaderAndReturnMessage(self::NOT_FOUND);
			}

			if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) >= 4007000) {
				$dataCharset = 'utf-8';
			} else {
				$dataCharset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset']
					? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : 'iso-8859-1';
			}
			$resultCharset = strtolower($this->configGetter->getConfValueString('charsetForCsv'));
			if ($dataCharset !== $resultCharset) {
				$result = $this->getCharsetConversion()->conv($result, $dataCharset, $resultCharset);
			}
		} catch (Exception $exception) {
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->addHeader('Status: 500 Internal Server Error');
			$result = $exception->getMessage() . LF . LF . $exception->getTraceAsString() . LF . LF;
		}

		return $result;
	}

	/**
	 * Initializes this object and its configuration getter.
	 *
	 * @param array $configuration TypoScript configuration for the plugin, may be empty
	 *
	 * @return void
	 */
	public function init(array $configuration = array()) {
		parent::init($configuration);

		if ($this->configGetter === NULL) {
			$this->configGetter = t3lib_div::makeInstance('tx_seminars_configgetter');
			$this->configGetter->init();
		}
	}

	/**
	 * Retrieves an active charset conversion instance.
	 *
	 * @return t3lib_cs a charset conversion instance
	 *
	 * @throws RuntimeException
	 */
	protected function getCharsetConversion() {
		if (isset($GLOBALS['TSFE'])) {
			$instance = $GLOBALS['TSFE']->csConvObj;
		} elseif (isset($GLOBALS['LANG'])) {
			$instance = $GLOBALS['LANG']->csConvObj;
		} else {
			throw new RuntimeException('There was neither a front end nor a back end detected.', 1333292438);
		}

		return $instance;
	}

	/**
	 * Loads the locallang files needed to translate the CSV headings.
	 *
	 * @return void
	 *
	 * @throws RuntimeException
	 */
	private function loadLocallangFiles() {
		if (is_object($GLOBALS['TSFE']) && is_array($this->LOCAL_LANG)) {
			$this->language = t3lib_div::makeInstance('language');
			if (!empty($this->LLkey)) {
				$this->language->init($this->LLkey);
			}
		} elseif (is_object($GLOBALS['LANG'])) {
			$this->language = $GLOBALS['LANG'];
		} else {
			throw new RuntimeException('The language could not be loaded. Please check your installation.', 1333292453);
		}

		$this->language->includeLLFile(t3lib_extMgm::extPath('seminars') . 'locallang_db.xml');
		$this->language->includeLLFile(t3lib_extMgm::extPath('lang') . 'locallang_general.xml');
	}

	/**
	 * Creates a CSV list of registrations for the event given in $eventUid, including a heading line.
	 *
	 * If the seminar does not exist, an error message is returned, and an error 404 is set.
	 *
	 * If access is denied, an error message is returned, and an error 403 is set.
	 *
	 * @param integer $eventUid UID of the event for which to create the CSV list, must be >= 0
	 *
	 * @return string CSV list of registrations for the given seminar or an error message in case of an error
	 */
	public function createAndOutputListOfRegistrations($eventUid = 0) {
		$pid = intval($this->piVars['pid']);
		if ($eventUid > 0) {
			if (!$this->hasAccessToEventAndItsRegistrations($eventUid)) {
				return $this->addErrorHeaderAndReturnMessage($this->errorType);
			}
		} else {
			if (!$this->canAccessRegistrationsOnPage($pid)) {
				return $this->addErrorHeaderAndReturnMessage(self::ACCESS_DENIED);
			}
		}

		$this->setContentTypeForRegistrationLists();
		if ($eventUid === 0) {
			$result = $this->createListOfRegistrationsOnPage($pid);
		} else {
			$result = $this->createListOfRegistrations($eventUid);
		}

		return $result;
	}

	/**
	 * Creates a CSV list of registrations for the event with the UID given in
	 * $eventUid, including a heading line.
	 *
	 * This function does not do any access checks.
	 *
	 * @param integer $eventUid UID of the event for which the registration list should be created, must be > 0
	 *
	 * @return string CSV list of registrations for the given seminar or an
	 *                empty string if there is not event with the provided UID
	 */
	public function createListOfRegistrations($eventUid) {
		if (!tx_seminars_OldModel_Abstract::recordExists($eventUid, 'tx_seminars_seminars')) {
			return '';
		}

		$registrationBagBuilder = $this->createRegistrationBagBuilder();
		$registrationBagBuilder->limitToEvent($eventUid);

		return $this->createRegistrationsHeading() . $this->getRegistrationsCsvList($registrationBagBuilder);
	}

	/**
	 * Returns the list of registrations as CSV separated values.
	 *
	 * The fields are separated by semicolons and the lines by CRLF.
	 *
	 * @param tx_seminars_BagBuilder_Registration $builder
	 *        the bag builder already limited to the registrations which should be returned
	 *
	 * @return string the list of registrations, will be empty if no registrations have been given
	 *
	 * @throws RuntimeException
	 */
	private function getRegistrationsCsvList(tx_seminars_BagBuilder_Registration $builder) {
		$result = '';
		/** @var $bag tx_seminars_Bag_Registration */
		$bag = $builder->build();

		/** @var $registration tx_seminars_registration */
		foreach ($bag as $registration) {
			switch ($this->getTypo3Mode()) {
				case 'BE':
					$hasAccess = $GLOBALS['BE_USER']->doesUserHaveAccess(
						t3lib_BEfunc::getRecord('pages', $registration->getPageUid()), 1
					);
					break;
				case 'FE':
					$hasAccess = TRUE;
					break;
				default:
					throw new RuntimeException(
						'You are trying to get a CSV list in an unsupported mode. Currently, only back-end and front-end mode ' .
							'are allowed.',
						1333292478
					);
			}

			if ($hasAccess) {
				$userData = $this->retrieveData($registration, 'getUserData', $this->getFrontEndUserFieldsConfiguration());
				$registrationData = $this->retrieveData(
					$registration, 'getRegistrationData', $this->getRegistrationFieldsConfiguration()
				);
				// Combines the arrays with the user and registration data
				// and creates a list of semicolon-separated values from them.
				$result .= implode(';', array_merge($userData, $registrationData)) . CRLF;
			}
		}

		return $result;
	}

	/**
	 * Creates the heading line for the list of registrations (including a CRLF at the end).
	 *
	 * @return string the heading line for the list of registrations, will not be empty
	 */
	protected function createRegistrationsHeading() {
		$fieldsFromFeUser = $this->localizeCsvHeadings(
			t3lib_div::trimExplode(',', $this->getFrontEndUserFieldsConfiguration(), TRUE), 'LGL'
		);
		$fieldsFromAttendances = $this->localizeCsvHeadings(
			t3lib_div::trimExplode(',', $this->getRegistrationFieldsConfiguration(), TRUE), 'tx_seminars_attendances'
		);

		$result = array_merge($fieldsFromFeUser, $fieldsFromAttendances);

		return implode(';', $result) . CRLF;
	}

	/**
	 * Creates a CSV list of events for the page given in $pid.
	 *
	 * If the page does not exist, an error message is returned, and an error 404 is set.
	 *
	 * If access is denied, an error message is returned, and an error 403 is set.
	 *
	 * @param integer $pid PID of the page with events for which to create the CSV list, must be > 0
	 *
	 * @return string CSV list of events for the given page or an error message in case of an error
	 */
	public function createAndOutputListOfEvents($pid) {
		if ($pid > 0) {
			if ($this->canAccessListOfEvents($pid)) {
				$this->setContentTypeForEventLists();
				$result = $this->createListOfEvents($pid);
			} else {
				$result = $this->addErrorHeaderAndReturnMessage(self::ACCESS_DENIED);
			}
		} else {
			$result = $this->addErrorHeaderAndReturnMessage(self::NOT_FOUND);
		}

		return $result;
	}

	/**
	 * Retrieves a list of events as CSV, including the header line.
	 *
	 * This function does not do any access checks.
	 *
	 * @param integer $pid PID of the system folder from which the event records should be exported, must be > 0
	 *
	 * @return string CSV export of the event records on that page
	 */
	public function createListOfEvents($pid) {
		if ($pid <= 0) {
			return '';
		}

		$result = $this->createEventsHeading();

		/** @var $builder tx_seminars_BagBuilder_Event */
		$builder = t3lib_div::makeInstance('tx_seminars_BagBuilder_Event');
		$builder->setBackEndMode();
		$builder->setSourcePages($pid, 255);

		foreach ($builder->build() as $seminar) {
			$seminarData = $this->retrieveData(
				$seminar, 'getEventData', $this->configGetter->getConfValueString('fieldsFromEventsForCsv')
			);
			// Creates a list of comma-separated values of the event data.
			$result .= implode(';', $seminarData) . CRLF;
		}

		return $result;
	}

	/**
	 * Creates the heading line for a CSV event list.
	 *
	 * @return string header list, will not be empty if the CSV export has been configured correctly
	 */
	private function createEventsHeading() {
		$eventFields = t3lib_div::trimExplode(',', $this->configGetter->getConfValueString('fieldsFromEventsForCsv'), TRUE);

		return implode(';', $this->localizeCsvHeadings($eventFields, 'tx_seminars_seminars')) . CRLF;
	}

	/**
	 * Retrieves data from an object and returns that data as an array of
	 * values. The individual values are already wrapped in double quotes, with
	 * the contents having all quotes escaped.
	 *
	 * @param tx_seminars_OldModel_Abstract $dataSupplier
	 *        object that will deliver the data
	 * @param string $supplierFunction
	 *        name of a function of the given object that expects a key as a parameter and returns the value for that
	 *        key as a string
	 * @param string $keys
	 *        comma-separated list of keys to retrieve
	 *
	 * @return array the data for the keys provided in $keys (may be empty)
	 */
	protected function retrieveData(tx_seminars_OldModel_Abstract $dataSupplier, $supplierFunction, $keys) {
		$result = array();

		if (($keys !== '') && method_exists($dataSupplier, $supplierFunction)) {
			$allKeys = t3lib_div::trimExplode(',', $keys);
			foreach ($allKeys as $currentKey) {
				$rawData = $dataSupplier->$supplierFunction($currentKey);
				// Escapes double quotes and wraps the whole string in double quotes.
				if (strpos($rawData, '"') !== FALSE) {
					$result[] = '"' . str_replace('"', '""', $rawData) . '"';
				} elseif ((strpos($rawData, ';') !== FALSE) || (strpos($rawData, LF) !== FALSE)) {
					$result[] = '"' . $rawData . '"';
				} else {
					$result[] = $rawData;
				}
			}
		}

		return $result;
	}

	/**
	 * Checks whether the list of registrations is accessible, ie.
	 * 1. CSV access is allowed for testing purposes, or
	 * 2. the logged-in BE user has read access to the registrations table and
	 *    read access to *all* pages where the registration records of the
	 *    selected event are stored.
	 *
	 * @param integer $eventUid UID of the event record for which access should be checked, must be > 0
	 *
	 * @return boolean TRUE if the list of registrations may be exported as CSV
	 */
	protected function canAccessListOfRegistrations($eventUid) {
		switch ($this->getTypo3Mode()) {
			case 'BE':
				/** @var $accessCheck Tx_Seminars_Csv_BackEndRegistrationAccessCheck */
				$accessCheck = t3lib_div::makeInstance('Tx_Seminars_Csv_BackEndRegistrationAccessCheck');
				$result = $accessCheck->hasAccess();
				break;
			case 'FE':
				/** @var $accessCheck Tx_Seminars_Csv_FrontEndRegistrationAccessCheck */
				$accessCheck = t3lib_div::makeInstance('Tx_Seminars_Csv_FrontEndRegistrationAccessCheck');

				/** @var $seminar tx_seminars_seminar */
				$seminar = t3lib_div::makeInstance('tx_seminars_seminar', $eventUid);
				$accessCheck->setEvent($seminar);

				$result = $accessCheck->hasAccess();
				break;
			default:
				$result = FALSE;
		}

		return $result;
	}

	/**
	 * Checks whether the logged-in BE user has access to the event list.
	 *
	 * @param integer $pageUid PID of the page with events for which to check access, must be >= 0
	 *
	 * @return boolean TRUE if the list of events may be exported as CSV, FALSE otherwise
	 */
	protected function canAccessListOfEvents($pageUid) {
		/** @var $accessCheck Tx_Seminars_Csv_BackEndEventAccessCheck */
		$accessCheck = t3lib_div::makeInstance('Tx_Seminars_Csv_BackEndEventAccessCheck');
		$accessCheck->setPageUid($pageUid);

		return $accessCheck->hasAccess();
	}

	/**
	 * Sets the HTTP header: the content type and filename (content disposition) for registration lists.
	 *
	 * @return void
	 */
	private function setContentTypeForRegistrationLists() {
		$this->setPageTypeAndDisposition($this->configGetter->getConfValueString('filenameForRegistrationsCsv'));
	}

	/**
	 * Sets the HTTP header: the content type and filename (content disposition) for event lists.
	 *
	 * @return void
	 */
	private function setContentTypeForEventLists() {
		$this->setPageTypeAndDisposition($this->configGetter->getConfValueString('filenameForEventsCsv'));
	}

	/**
	 * Sets the page's content type to CSV and the page's content disposition to the given filename.
	 *
	 * Adds the data directly to the page header.
	 *
	 * @param string $csvFileName the name for the page which is used as storage name, must not be empty
	 *
	 * @return void
	 */
	private function setPageTypeAndDisposition($csvFileName) {
		tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->addHeader(
			'Content-type: text/csv; header=present; charset=' .
			$this->configGetter->getConfValueString('charsetForCsv')
		);
		tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->addHeader(
			'Content-disposition: attachment; filename=' . $csvFileName
		);
	}

	/**
	 * Returns our config getter (which might be NULL if we aren't initialized
	 * properly yet).
	 *
	 * This function is intended for testing purposes only.
	 *
	 * @return tx_seminars_configgetter our config getter, might be NULL
	 */
	public function getConfigGetter() {
		return $this->configGetter;
	}

	/**
	 * Adds a status header and returns an error message.
	 *
	 * @param integer $errorCode
	 *        the type of error message, must be tx_seminars_pi2::ACCESS_DENIED or tx_seminars_pi2::NOT_FOUND
	 *
	 * @return string the error message belonging to the error code, will not be empty
	 *
	 * @throws InvalidArgumentException
	 */
	private function addErrorHeaderAndReturnMessage($errorCode) {
		switch ($errorCode) {
			case self::ACCESS_DENIED:
				tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->addHeader('Status: 403 Forbidden');
				$result = $this->translate('message_403');
				break;
			case self::NOT_FOUND:
				tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->addHeader('Status: 404 Not Found');
				$result = $this->translate('message_404');
				break;
			default:
				throw new InvalidArgumentException('"' . $errorCode . '" is no legal error code.', 1333292523);
		}

		return $result;
	}

	/**
	 * Checks whether the currently logged-in BE-User is allowed to access the registrations records on the given page.
	 *
	 * @param integer $pageUid PID of the page to check the access for, must be >= 0
	 *
	 * @return boolean
	 *         TRUE if the currently logged-in BE-User is allowed to access the registrations records,
	 *         FALSE if the user has no access or this function is called in FE mode
	 */
	private function canAccessRegistrationsOnPage($pageUid) {
		switch ($this->getTypo3Mode()) {
			case 'BE':
				/** @var $accessCheck Tx_Seminars_Csv_BackEndRegistrationAccessCheck */
				$accessCheck = t3lib_div::makeInstance('Tx_Seminars_Csv_BackEndRegistrationAccessCheck');
				$accessCheck->setPageUid($pageUid);
				$result = $accessCheck->hasAccess();
				break;
			case 'FE':
				// The fall-through is intentional.
			default:
				$result = FALSE;
		}

		return $result;
	}

	/**
	 * Creates a CSV list of registrations for the given page and its subpages,
	 * including a heading line.
	 *
	 * @param integer $pid
	 *        the PID of the page to export the registrations for, must be >= 0
	 *
	 * @return string CSV list of registrations for the given page, will be
	 *                empty if no registrations could be found on the given page
	 *                and its subpages
	 */
	private function createListOfRegistrationsOnPage($pid) {
		$registrationsBagBuilder = $this->createRegistrationBagBuilder();
		$registrationsBagBuilder->setSourcePages($pid, self::RECURSION_DEPTH);

		return $this->createRegistrationsHeading() . $this->getRegistrationsCsvList($registrationsBagBuilder);
	}

	/**
	 * Creates a registrationBagBuilder with some preset limitations.
	 *
	 * @return tx_seminars_BagBuilder_Registration the bag builder with some preset limitations
	 */
	private function createRegistrationBagBuilder() {
		/** @var $registrationBagBuilder tx_seminars_BagBuilder_Registration */
		$registrationBagBuilder = t3lib_div::makeInstance('tx_seminars_BagBuilder_Registration');

		if (!$this->getRegistrationsOnQueueConfiguration()) {
			$registrationBagBuilder->limitToRegular();
		}

		$registrationBagBuilder->limitToExistingUsers();

		return $registrationBagBuilder;
	}

	/**
	 * Returns the mode currently set in TYPO3_MODE.
	 *
	 * @return string either "FE" or "BE" representing the TYPO3 mode
	 */
	private function getTypo3Mode() {
		if ($this->typo3Mode !== '') {
			return $this->typo3Mode;
		}

		return TYPO3_MODE;
	}

	/**
	 * Sets the TYPO3_MODE.
	 *
	 * The value is stored in the member variable $this->typo3Mode
	 *
	 * This function is for testing purposes only!
	 *
	 * @param string $typo3Mode the TYPO3_MODE to set, must be "BE" or "FE"
	 *
	 * @return void
	 */
	public function setTypo3Mode($typo3Mode) {
		$this->typo3Mode = $typo3Mode;
	}

	/**
	 * Checks whether the currently logged in BE-User has access to the given
	 * event and its registrations.
	 *
	 * Stores the type of the error in $this->errorType
	 *
	 * @param integer $eventUid
	 *        the event to check the access for, must be >= 0 but not
	 *        necessarily point to an existing event
	 *
	 * @return boolean TRUE if the event record exists and the BE-User has
	 *                 access to the registrations belonging to the event,
	 *                 FALSE otherwise
	 */
	private function hasAccessToEventAndItsRegistrations($eventUid) {
		$result = FALSE;

		if (!tx_seminars_OldModel_Abstract::recordExists($eventUid, 'tx_seminars_seminars')) {
			$this->errorType = self::NOT_FOUND;
		} elseif (!$this->canAccessListOfRegistrations($eventUid)) {
			$this->errorType = self::ACCESS_DENIED;
		} else {
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Sets the mode of the CSV export.
	 *
	 * @param string $exportMode
	 *        the export mode, must be either tx_seminars_pi2::EXPORT_MODE_WEB or tx_seminars_pi2::EXPORT_MODE_EMAIL
	 *
	 * @return void
	 */
	public function setExportMode($exportMode) {
		$this->exportMode = ($exportMode === self::EXPORT_MODE_EMAIL) ? self::EXPORT_MODE_EMAIL : self::EXPORT_MODE_WEB;
	}

	/**
	 * Gets the fields which should be used from the fe_users table for the CSV files.
	 *
	 * @return string the fe_user table fields to use in the CSV file, will be empty if no fields were set.
	 */
	private function getFrontEndUserFieldsConfiguration() {
		switch ($this->exportMode) {
			case self::EXPORT_MODE_EMAIL:
				$configurationVariable = 'fieldsFromFeUserForEmailCsv';
				break;
			default:
				$configurationVariable = 'fieldsFromFeUserForCsv';
		}

		return $this->configGetter->getConfValueString($configurationVariable);
	}

	/**
	 * Returns the fields which should be used from the attendances table for
	 * the CSV attachment.
	 *
	 * @return string the attendance table fields to use in the CSV attachment,
	 *                will be empty if no fields were set.
	 */
	private function getRegistrationFieldsConfiguration() {
		switch ($this->exportMode) {
			case self::EXPORT_MODE_EMAIL:
				$configurationVariable = 'fieldsFromAttendanceForEmailCsv';
				break;
			default:
				$configurationVariable = 'fieldsFromAttendanceForCsv';
		}

		return $this->configGetter->getConfValueString($configurationVariable);
	}

	/**
	 * Returns whether the attendances on queue should also be exported in the
	 * CSV file.
	 *
	 * @return boolean TRUE if the attendances on queue should also be exported,
	 *                 FALSE otherwise
	 */
	private function getRegistrationsOnQueueConfiguration() {
		switch ($this->exportMode) {
			case self::EXPORT_MODE_EMAIL:
				$configurationVariable = 'showAttendancesOnRegistrationQueueInEmailCsv';
				break;
			default:
				$configurationVariable = 'showAttendancesOnRegistrationQueueInCSV';
		}

		return $this->configGetter->getConfValueBoolean($configurationVariable);
	}

	/**
	 * Returns the localized field names.
	 *
	 * @param array $fieldNames the field names to translate, may be empty
	 * @param string $tableName the table to which the fields belong to
	 *
	 * @return array the translated field names in an array, will be empty if no field names were given
	 */
	private function localizeCsvHeadings(array $fieldNames, $tableName) {
		if (empty($fieldNames)) {
			return array();
		}

		$result = array();
		foreach ($fieldNames as $fieldName) {
			$translation = trim($this->language->getLL($tableName . '.' . $fieldName));

			if (substr($translation, -1) === ':') {
				$translation = substr($translation, 0, -1);
			}

			$result[] = $translation;
		}

		return $result;
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/seminars/pi2/class.tx_seminars_pi2.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/seminars/pi2/class.tx_seminars_pi2.php']);
}