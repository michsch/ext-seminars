<?php
/***************************************************************
* Copyright notice
*
* (c) 2010-2014 Oliver Klee <typo3-coding@oliverklee.de>
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

/**
 * This class provides functions for creating the link/URL to the single view page of an event.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class tx_seminars_Service_SingleViewLinkBuilder {
	/**
	 * a plugin instance that provides access to the flexforms plugin settings
	 *
	 * @var tslib_pibase
	 */
	private $plugin = NULL;

	/**
	 * whether this class has created a fake front end which needs to get
	 * cleaned up again
	 *
	 * @var bool
	 */
	private $hasFakeFrontEnd = FALSE;

	/**
	 * The destructor.
	 */
	public function __destruct() {
		unset($this->plugin);

		if ($this->hasFakeFrontEnd) {
			$this->discardFakeFrontEnd();
		}
	}

	/**
	 * Discards the fake front end.
	 *
	 * This function nulls out $GLOBALS['TSFE'] and $GLOBALS['TT']. In addition,
	 * any logged-in front-end user will be logged out.
	 *
	 * @return void
	 */
	protected function discardFakeFrontEnd() {
		unset(
			$GLOBALS['TSFE']->tmpl, $GLOBALS['TSFE']->sys_page,
			$GLOBALS['TSFE']->fe_user, $GLOBALS['TSFE']->TYPO3_CONF_VARS,
			$GLOBALS['TSFE']->config, $GLOBALS['TSFE']->TCAcachedExtras,
			$GLOBALS['TSFE']->imagesOnPage, $GLOBALS['TSFE']->cObj,
			$GLOBALS['TSFE']->csConvObj, $GLOBALS['TSFE']->pagesection_lockObj,
			$GLOBALS['TSFE']->pages_lockObj
		);
		$GLOBALS['TSFE'] = NULL;
		$GLOBALS['TT'] = NULL;

		$this->hasFakeFrontEnd = FALSE;
	}

	/**
	 * Sets the plugin used accessing to the flexforms plugin settings.
	 *
	 * @param tslib_pibase $plugin a seminars plugin instance
	 *
	 * @return void
	 */
	public function setPlugin(tslib_pibase $plugin) {
		$this->plugin = $plugin;
	}

	/**
	 * Returns the plugin used for accessing the flexforms plugin settings.
	 *
	 * @return tx_oelib_templatehelper
	 *         the plugin, will be NULL if non has been set via setPlugin
	 *
	 * @see setPlugin
	 */
	protected function getPlugin() {
		return $this->plugin;
	}

	/**
	 * Creates the absolute URL to the single view of the event $event.
	 *
	 * @param tx_seminars_Model_Event $event the event to create the link for
	 *
	 * @return string
	 *         the absolute URL for the event's single view, not htmlspecialchared
	 */
	public function createAbsoluteUrlForEvent(tx_seminars_Model_Event $event) {
		return t3lib_div::locationHeaderUrl(
			$this->createRelativeUrlForEvent($event)
		);
	}

	/**
	 * Creates the relative URL to the single view of the event $event.
	 *
	 * @param tx_seminars_Model_Event $event the event to create the link for
	 *
	 * @return string
	 *         the relative URL for the event's single view, not htmlspecialchared
	 */
	public function createRelativeUrlForEvent(tx_seminars_Model_Event $event) {
		$linkConfiguration = array(
			'parameter' => $this->getSingleViewPageForEvent($event),
			'additionalParams' => t3lib_div::implodeArrayForUrl(
				'tx_seminars_pi1',
				array('showUid' => $event->getUid()),
				'',
				FALSE,
				TRUE
			)
		);

		return $this->getContentObject()->typoLink_URL($linkConfiguration);
	}

	/**
	 * Retrieves a content object to be used for creating typolinks.
	 *
	 * @return tslib_cObj a content object for creating typolinks
	 */
	protected function getContentObject() {
		if (!isset($GLOBALS['TSFE']) || !is_object($GLOBALS['TSFE'])) {
			$this->createFakeFrontEnd();
		}

		return $GLOBALS['TSFE']->cObj;
	}

	/**
	 * Creates an artificial front end (which is necessary for creating
	 * typolinks).
	 *
	 * @return void
	 */
	protected function createFakeFrontEnd() {
		$this->suppressFrontEndCookies();

		$GLOBALS['TT'] = t3lib_div::makeInstance('t3lib_TimeTrackNull');

		/** @var $frontEnd tslib_fe */
		$frontEnd = t3lib_div::makeInstance(
			'tslib_fe', $GLOBALS['TYPO3_CONF_VARS'], 0, 0
		);

		// simulates a normal FE without any logged-in FE or BE user
		$frontEnd->beUserLogin = FALSE;
		$frontEnd->workspacePreview = '';
		$frontEnd->initFEuser();
		$frontEnd->determineId();
		$frontEnd->initTemplate();
		$frontEnd->config = array();

		$frontEnd->tmpl->getFileName_backPath = PATH_site;

		$frontEnd->newCObj();

		$GLOBALS['TSFE'] = $frontEnd;

		$this->hasFakeFrontEnd = TRUE;
	}

	/**
	 * Makes sure that no FE login cookies will be sent.
	 *
	 * @return void
	 */
	private function suppressFrontEndCookies() {
		$_POST['FE_SESSION_KEY'] = '';
		$_GET['FE_SESSION_KEY'] = '';
		$GLOBALS['TYPO3_CONF_VARS']['FE']['dontSetCookie'] = 1;
	}

	/**
	 * Gets the single view page UID/URL from $event (if any single view page is set for
	 * the event) or from the configuration.
	 *
	 * @param tx_seminars_Model_Event $event the event for which to get the single view page
	 *
	 * @return string
	 *         the single view page UID/URL for $event, will be empty if neither
	 *         the event nor the configuration has any single view page set
	 */
	protected function getSingleViewPageForEvent(tx_seminars_Model_Event $event) {
		if ($event->hasCombinedSingleViewPage()) {
			$result = $event->getCombinedSingleViewPage();
		} elseif ($this->configurationHasSingleViewPage()) {
			$result = (string) $this->getSingleViewPageFromConfiguration();
		} else {
			$result = '';
		}

		return $result;
	}

	/**
	 * Checks whether there is a single view page set in the configuration.
	 *
	 * @return bool
	 *         TRUE if a single view page has been set in the configuration,
	 *         FALSE otherwise
	 */
	protected function configurationHasSingleViewPage() {
		return ($this->getSingleViewPageFromConfiguration() > 0);
	}

	/**
	 * Retrieves the single view page UID from the flexforms/TS Setup
	 * configuration.
	 *
	 * @return int
	 *         the single view page UID from the configuration, will be 0 if no
	 *         page UID has been set
	 */
	protected function getSingleViewPageFromConfiguration() {
		if ($this->plugin !== NULL) {
			$result = $this->getPlugin()->getConfValueInteger('detailPID');
		} else {
			$result = tx_oelib_ConfigurationRegistry
				::get('plugin.tx_seminars_pi1')->getAsInteger('detailPID');
		}

		return $result;
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/seminars/Service/SingleViewLinkBuilder.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/seminars/Service/SingleViewLinkBuilder.php']);
}