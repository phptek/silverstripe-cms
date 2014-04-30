<?php
/**
 * User Security Report Tests
 *
 * @package cms
 * @subpackage tests
 * @author Michael Parkhill <mike@silverstripe.com>
 * @author Russell Michell <russ@silverstripe.com>
 */
class UserSecurityReportTest extends SapphireTest {

	protected static $fixture_file = 'cms/tests/reports/UserSecurityReportTest.yml';
	protected $records;
	protected $report;

	/**
	 * Utility method for all tests to use.
	 *
	 * @return \ArrayList
	 * @todo pre-fill the report with fixture-defined users
	 */
	public function setUp() {
		parent::setUp();
		$reports = SS_Report::get_reports();
		$report = $reports['UserSecurityReport'];
		$this->report = $report;
		$this->records = $report->sourceRecords()->toArray();
	}

	public function testSourceRecords() {
		$this->assertNotEmpty($this->records);
	}

	public function testGetLastVisitedStatus() {
		$member = $this->objFromFixture('Member', 'member-last-visited-is-string');
		$lastVisited = $this->report->getLastVisitedStatus($member->LastVisited);
		$this->assertNotNull($lastVisited);
		$this->assertEquals('2013-02-26 11:22:10', $lastVisited);

		$member = $this->objFromFixture('Member', 'member-last-visited-is-empty');
		$lastVisited = $this->report->getLastVisitedStatus($member->LastVisited);
		$this->assertEquals('Never', $lastVisited);
	}

	public function testGetMemberGroups() {
		$member = $this->objFromFixture('Member', 'member-has-0-groups');
		$groups = $this->report->getMemberGroups($member);
		$this->assertEquals('Not in a Security Group', $groups);

		$member = $this->objFromFixture('Member', 'member-has-1-groups');
		$groups = $this->report->getMemberGroups($member);
		$this->assertEquals('Group Test 01', $groups);
	}

	public function testGetMemberPermissions() {
		$member = $this->objFromFixture('Member', 'member-has-0-permissions');
		$perms = $this->report->getMemberPermissions($member);
		$this->assertEquals('No Permissions', $perms);

		$member = $this->objFromFixture('Member', 'member-has-1-permissions');
		$perms = $this->report->getMemberPermissions($member);
		$this->assertEquals('Full administrative rights', $perms);

		$member = $this->objFromFixture('Member', 'member-has-n-permissions');
		$perms = $this->report->getMemberPermissions($member);
		$this->assertEquals('Full administrative rights, Change site structure', $perms);
	}
}
