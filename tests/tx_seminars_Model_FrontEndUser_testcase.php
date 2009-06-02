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

require_once(t3lib_extMgm::extPath('oelib') . 'class.tx_oelib_Autoloader.php');

/**
 * Testcase for the tx_seminars_Model_FrontEndUser class in the 'seminars' extension.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Bernd Schönbach <bernd@oliverklee.de>
 */
class tx_seminars_Model_FrontEndUser_testcase extends tx_phpunit_testcase {
	/**
	 * @var tx_seminars_Model_FrontEndUser the object to test
	 */
	private $fixture;

	public function setUp() {
		$this->fixture = new tx_seminars_Model_FrontEndUser();
		$this->testingFramework = new tx_oelib_testingFramework('tx_seminars');
	}

	public function tearDown() {
		$this->testingFramework->cleanUp();
		$this->fixture->__destruct();
		unset($this->fixture);
	}


	////////////////////////////////////////
	// Tests concerning getPublishSettings
	////////////////////////////////////////

	public function test_getPublishSettings_ForUserWithOneGroupAndGroupPublishSettingZero_ReturnsPublishAll() {
		$groupMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_FrontEndUserGroup');
		$userGroup = $groupMapper->getNewGhost();
		$userGroup->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_IMMEDIATELY
		));

		$list = new tx_oelib_List();
		$list->add($userGroup);
		$this->fixture->setData(array('usergroup' => $list));

		$this->assertEquals(
			tx_seminars_Model_FrontEndUserGroup::PUBLISH_IMMEDIATELY,
			$this->fixture->getPublishSetting()
		);
	}

	public function test_getPublishSettings_ForUserWithOneGroupAndGroupPublishSettingOne_ReturnsHideNew() {
		$groupMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_FrontEndUserGroup');
		$userGroup = $groupMapper->getNewGhost();
		$userGroup->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_NEW
		));

		$list = new tx_oelib_List();
		$list->add($userGroup);
		$this->fixture->setData(array('usergroup' => $list));

		$this->assertEquals(
			tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_NEW,
			$this->fixture->getPublishSetting()
		);
	}

	public function test_getPublishSettings_ForUserWithOneGroupAndGroupPublishSettingTwo_ReturnsHideEdited() {
		$groupMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_FrontEndUserGroup');
		$userGroup = $groupMapper->getNewGhost();
		$userGroup->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_EDITED
		));

		$list = new tx_oelib_List();
		$list->add($userGroup);
		$this->fixture->setData(array('usergroup' => $list));

		$this->assertEquals(
			tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_EDITED,
			$this->fixture->getPublishSetting()
		);
	}

	public function test_getPublishSettings_ForUserWithoutGroup_ReturnsPublishAll() {
		$list = new tx_oelib_List();
		$this->fixture->setData(array('usergroup' => $list));

		$this->assertEquals(
			tx_seminars_Model_FrontEndUserGroup::PUBLISH_IMMEDIATELY,
			$this->fixture->getPublishSetting()
		);
	}

	public function test_getPublishSettings_ForUserWithTwoGroupsAndGroupPublishSettingZeroAndOne_ReturnsHideNew() {
		$groupMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_FrontEndUserGroup');
		$userGroup = $groupMapper->getNewGhost();
		$userGroup->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_IMMEDIATELY
		));

		$userGroup2 = $groupMapper->getNewGhost();
		$userGroup2->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_NEW
		));

		$list = new tx_oelib_List();
		$list->add($userGroup);
		$list->add($userGroup2);
		$this->fixture->setData(array('usergroup' => $list));

		$this->assertEquals(
			tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_NEW,
			$this->fixture->getPublishSetting()
		);
	}

	public function test_getPublishSettings_ForUserWithTwoGroupsAndGroupPublishSettingOneAndTwo_ReturnsHideEdited() {
		$groupMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_FrontEndUserGroup');
		$userGroup = $groupMapper->getNewGhost();
		$userGroup->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_NEW
		));

		$userGroup2 = $groupMapper->getNewGhost();
		$userGroup2->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_EDITED
		));

		$list = new tx_oelib_List();
		$list->add($userGroup);
		$list->add($userGroup2);
		$this->fixture->setData(array('usergroup' => $list));

		$this->assertEquals(
			tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_EDITED,
			$this->fixture->getPublishSetting()
		);
	}

	public function test_getPublishSettings_ForUserWithTwoGroupsAndGroupPublishSettingTwoAndZero_ReturnsHideEdited() {
		$groupMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_FrontEndUserGroup');
		$userGroup = $groupMapper->getNewGhost();
		$userGroup->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_EDITED
		));

		$userGroup2 = $groupMapper->getNewGhost();
		$userGroup2->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_IMMEDIATELY
		));

		$list = new tx_oelib_List();
		$list->add($userGroup);
		$list->add($userGroup2);
		$this->fixture->setData(array('usergroup' => $list));

		$this->assertEquals(
			tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_EDITED,
			$this->fixture->getPublishSetting()
		);
	}

	public function test_getPublishSettings_ForUserWithTwoGroupsAndBothGroupPublishSettingsOne_ReturnsHideNew() {
		$groupMapper = tx_oelib_MapperRegistry::get('tx_seminars_Mapper_FrontEndUserGroup');
		$userGroup = $groupMapper->getNewGhost();
		$userGroup->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_NEW
		));

		$userGroup2 = $groupMapper->getNewGhost();
		$userGroup2->setData(array(
			'tx_seminars_publish_events'
				=> tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_NEW
		));

		$list = new tx_oelib_List();
		$list->add($userGroup);
		$list->add($userGroup2);
		$this->fixture->setData(array('usergroup' => $list));

		$this->assertEquals(
			tx_seminars_Model_FrontEndUserGroup::PUBLISH_HIDE_NEW,
			$this->fixture->getPublishSetting()
		);
	}
}
?>