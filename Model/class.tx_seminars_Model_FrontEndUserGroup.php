<?php
/***************************************************************
* Copyright notice
*
* (c) 2009 Bernd Schönbach <bernd@oliverklee.de>
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
 * Class 'tx_seminars_Model_FrontEndUserGroup' for the 'seminars' extension.
 *
 * This class represents a front-end usergroup.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Bernd Schönbach <bernd@oliverklee.de>
 */
class tx_seminars_Model_FrontEndUserGroup extends tx_oelib_Model_FrontEndUserGroup {
	/**
	 * @var integer the publish setting to immediately publish all events edited
	 */
	const PUBLISH_IMMEDIATELY = 0;

	/**
	 * @var integer the publish setting for hiding only new events created
	 */
	const PUBLISH_HIDE_NEW = 1;

	/**
	 * @var integer the publish setting for hiding newly created and edited
	 *              events
	 */
	const PUBLISH_HIDE_EDITED = 2;

	/**
	 * Returns the setting for event publishing.
	 *
	 * If no publish settings have been set, PUBLISH_IMMEDIATELY is returned.
	 *
	 * @return integer the class constants PUBLISH_IMMEDIATELY, PUBLISH_HIDE_NEW
	 *                 or PUBLISH_HIDE_EDITED
	 */
	public function getPublishSetting() {
		return $this->getAsInteger('tx_seminars_publish_events');
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/Model/class.tx_seminars_Model_FrontEndUserGroup.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/Model/class.tx_seminars_Model_FrontEndUserGroup.php']);
}
?>