<?php
/**
 * User Security Report
 *
 * @package cms
 * @subpackage content
 * @author Michael Parkhill <mike@silverstripe.com>
 */
class UserSecurityReport extends SS_Report {

	/**
	 * Columns in the report
	 *
	 * @var array
	 * @static
	 */
	protected static $columns = array(
		'ID' => 'User ID',
		'FirstName' => 'First Name',
		'Surname' => 'Surname',
		'Email' => 'Email',
		'Created' => 'Date Created',
		'LastVisited' => 'Last Visit',
		'Groups' => 'Groups',
		'Permissions' => 'Permissions'
	);

	/**
	 * Returns the report title
	 *
	 * @return string
	 */
	public function title() {
		return _t('UserSecurityReport.REPORTTITLE', 'Users, Groups and Permissions');
	}

	/**
	 * Builds a report description which is the current hostname with the current date and time
	 *
	 * @return string e.g. localhost/sitename - 21/12/2112
	 */
	public function description() {
		return str_replace(array('http', 'https', '://'), '', Director::protocolAndHost() . ' - ' . date('d/m/Y H:i:s'));
	}

	/**
	 * Returns the column names of the report
	 *
	 * @return array
	 */
	public function columns() {
		return self::$columns;
	}

	/**
	 * Alias of columns(), to support the export to csv action
	 * in {@link GridFieldExportButton} generateExportFileData method.
	 *
	 * @return array
	 */
	public function getColumns() {
		return self::$columns;
	}

	/**
	 * Another alias of columns()
	 *
	 * @var array
	 * @static
	 */
	public function summaryFields() {
		return $this->columns;
	}

	/**
	 * Defines the sortable columns on the report gridfield
	 *
	 * @return array
	 */
	public function sortColumns() {
		return array(
			'ID',
			'FirstName',
			'Surname',
			'Email',
			'Created',
			'LastVisited',
			'Groups',
			'Permissions',
		);
	}

	/**
	 * Builds the source records list for the report gridfield
	 *
	 * @return ArrayList
	 */
	public function sourceRecords() {
		// Get members sorted by ID
		$members = Member::get()->sort('ID');
		// Create an array list to store the report data rows
		$sourceRecords = new ArrayList();
		// Iterate the members list
		foreach($members as $member) {
			// collect group and permission info for each member
			$memberInfo = new DataObject(array(
				'ID' => $member->ID,
				'FirstName' => $member->FirstName,
				'Surname' => $member->Surname,
				'Email' => $member->Email,
				'Created' => $member->Created,
				'LastVisited' => $this->getLastVisitedStatus($member->LastVisited),
				'Groups' => $this->getMemberGroups($member),
				'Permissions' => $this->getMemberPermissions($member),
			));
			$sourceRecords->push($memberInfo);
		}
		return $sourceRecords;
	}

	/**
	 * Returns a status message for a last visited date
	 *
	 * @param string $lastVisited
	 * @return string
	 */
	public function getLastVisitedStatus($lastVisited) {
		if(!$lastVisited) {
			return _t('UserSecurityReport_NEVER', 'Never');
		}
		return $lastVisited;
	}

	/**
	 * Builds a comma separated list of member group names for a given Member.
	 *
	 * @param \Member $member
	 * @return string
	 */
	public function getMemberGroups($member) {
		// Get the member's groups, if any
		$groups = $member->Groups();
		// If no groups then return a status label
		if($groups->Count() == 0) {
			return _t('UserSecurityReport_NOGROUPS', 'Not in a Security Group');
		}
		// Collect the group names
		$groupNames = array();
		foreach($groups as $group) {
			$groupNames[] = $group->getTreeTitle();
		}
		// return a csv string of the group names, sans-markup
		return preg_replace("#</?[^>]>#", '', implode(', ', $groupNames));
	}

	/**
	 * Builds a comma separated list of human-readable CMS Security permissions for a given Member.
	 *
	 * @param \Member $member
	 * @return string
	 */
	public function getMemberPermissions($member) {
		$permissionsUsr = Permission::permissions_for_member($member->ID);
		$permissionsSrc = Permission::get_codes(true);
		
		$permissionNames = array();
		foreach($permissionsUsr as $code) {
			$code = strtoupper($code);
			foreach($permissionsSrc as $k => $v) {
				if(isset($v[$code])) {
					$name = (!empty($v[$code]['name']) ? $v[$code]['name'] : 'Unknown');
					$permissionNames[] = $name;
				}
			}
		}

		if(!count($permissionNames)) {
			return _t('UserSecurityReport_NOPERMISSIONS', 'No Permissions');
		}

		return implode(', ', $permissionNames);
	}

	/**
	 * Restrict access to this report to users with security admin access
	 * @param Member $member
	 * @return boolean
	 */
	public function canView($member = null) {
		if(Permission::checkMember($member, "CMS_ACCESS_SecurityAdmin")) {
			return true;
		}
		return false;
	}

	/**
	 * Return a field, such as a {@link GridField} that is
	 * used to show and manipulate data relating to this report.
	 *
	 * @return FormField subclass
	 */
	public function getReportField() {
		$gridField = parent::getReportField();
		$gridField->setModelClass('UserSecurityReport');
		$gridConfig = $gridField->getConfig();
		$gridConfig->removeComponentsByType('GridFieldPrintButton');
		$gridConfig->removeComponentsByType('GridFieldExportButton');
		$gridConfig->addComponents(
			new GridFieldPrintButton('buttons-after-left'),
			new GridFieldExportButton('buttons-after-left')
		);
		return $gridField;
	}

}
