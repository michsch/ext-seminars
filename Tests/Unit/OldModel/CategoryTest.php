<?php
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Test case.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class Tx_Seminars_Tests_Unit_OldModel_CategoryTest extends Tx_Phpunit_TestCase {
	/**
	 * @var Tx_Seminars_OldModel_Category
	 */
	private $fixture;
	/**
	 * @var Tx_Oelib_TestingFramework
	 */
	private $testingFramework;
	/**
	 * UID of the fixture's data in the DB
	 *
	 * @var int
	 */
	private $fixtureUid = 0;

	protected function setUp() {
		$this->testingFramework = new Tx_Oelib_TestingFramework('tx_seminars');
		$this->fixtureUid = $this->testingFramework->createRecord(
			'tx_seminars_categories',
			array('title' => 'Test category')
		);
	}

	protected function tearDown() {
		$this->testingFramework->cleanUp();
	}

	public function testCreateFromUid() {
		$this->fixture = new Tx_Seminars_OldModel_Category($this->fixtureUid);

		self::assertTrue(
			$this->fixture->isOk()
		);
	}

	public function testCreateFromUidFailsForInvalidUid() {
		$this->fixture = new Tx_Seminars_OldModel_Category($this->fixtureUid + 99);

		self::assertFalse(
			$this->fixture->isOk()
		);
	}

	public function testCreateFromUidFailsForZeroUid() {
		$this->fixture = new Tx_Seminars_OldModel_Category(0);

		self::assertFalse(
			$this->fixture->isOk()
		);
	}

	public function testCreateFromDbResult() {
		$dbResult = Tx_Oelib_Db::select(
			'*',
			'tx_seminars_categories',
			'uid = '.$this->fixtureUid
		);

		$this->fixture = new Tx_Seminars_OldModel_Category(0, $dbResult);

		self::assertTrue(
			$this->fixture->isOk()
		);
	}

	public function testCreateFromDbResultFailsForNull() {
		$this->fixture = new Tx_Seminars_OldModel_Category(0, null);

		self::assertFalse(
			$this->fixture->isOk()
		);
	}

	public function testGetTitle() {
		$this->fixture = new Tx_Seminars_OldModel_Category($this->fixtureUid);

		self::assertEquals(
			'Test category',
			$this->fixture->getTitle()
		);
	}

	public function testGetIconReturnsIcon() {
		$this->fixture = new Tx_Seminars_OldModel_Category(
			$this->testingFramework->createRecord(
				'tx_seminars_categories',
				array(
					'title' => 'Test category',
					'icon' => 'foo.gif',
				)
			)
		);

		self::assertEquals(
			'foo.gif',
			$this->fixture->getIcon()
		);
	}

	public function testGetIconReturnsEmptyStringIfCategoryHasNoIcon() {
		$this->fixture = new Tx_Seminars_OldModel_Category($this->fixtureUid);

		self::assertEquals(
			'',
			$this->fixture->getIcon()
		);
	}
}