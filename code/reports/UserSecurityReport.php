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
	 * @return string
	 */
	public function title() {
		return _t('UserSecurityReport.REPORTTITLE','Users, Groups and Permissions');
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
	 * @return array
	 */
	public function columns() {
		return self::$columns;
	}

	/**
	 * Alias of columns(), to support the export to csv action
	 * in {@link GridFieldExportButton} generateExportFileData method.
	 * @return array
	 */
	public function getColumns() {
		return self::$columns;
	}

	/**
	 *
	 * @var array
	 * @static
	 */
	public function summaryFields() {
		return $this->columns;
	}

	/**
	 * Defines the sortable columns on the report gridfield
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
	 * Get the source records for the report gridfield
	 * @return ArrayList
	 */
	public function sourceRecords() {
		// Get members sorted by ID
		$members = Member::get()->sort('ID');
		// Create an array list to store the report data rows
		$sourceRecords = new ArrayList();
		// Iterate the members list
		foreach ($members as $member) {
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
	 * @param string $lastVisited
	 * @return string
	 */
	public function getLastVisitedStatus($lastVisited) {
		if (!$lastVisited) {
			return _t('UserSecurityReport_NEVER', 'never');
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
		if ($groups->Count() == 0) {
			return _t('UserSecurityReport_NOGROUPS', 'Not in a Security Group');
		}
		
		// Collect the group names
		$groupNames = array();
		foreach ($groups as $group) {
			$groupNames[] = $group->getTreeTitle();
		}
		
		// return a csv string of the group names, sans-markup
		return preg_replace("#</?[^>]>#", '', implode(', ', $groupNames));
	}

	/**
	 * Builds a comma separated list of human-readbale permissions for a given Member.
	 * 
	 * @param \Member $member
	 * @return string
	 */
	public function getMemberPermissions($member) {
		$permissionsUsr = Permission::permissions_for_member($member->ID);
		/*
		 * Notes: 
		 * - Permission::get_declared_permissions_list() always returns null.
		 * - Only alternative is to do it how it's done on the Member class. 
		 */
		$permissionsSys = new PermissionCheckboxSetField_Readonly('', '', '', 'GroupID', $member->getManyManyComponents('Groups'));
		$permissionsSrc = $permissionsSys->source;
		
		$permissionNames = array();
		foreach ($permissionsUsr as $code) {
			$code = strtoupper($code);
			foreach($permissionsSrc as $k=>$v) {
				if(isset($v[$code])) {
					$name = (isset($v[$code]['name']) && !empty($v[$code]['name']) ? $v[$code]['name'] : 'Unknown');
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
			new GridFieldPrintReportButton('buttons-after-left'),
			new GridFieldExportReportButton('buttons-after-left')
		);
		return $gridField;
	}

} //end class UserSecurityReport

/**
 * An extension to GridFieldExportButton to support downloading a custom Report as a CSV file
 *
 * This code was adapted from a solution posted in SilverStripe.org forums:
 * http://www.silverstripe.org/customising-the-cms/show/38202
 *
 * @package cms
 * @subpackage content
 * @author Michael Armstrong <http://www.silverstripe.org/ForumMemberProfile/show/30887>
 * @author Michael Parkhill <mike@silverstripe.com>
 */
if (!class_exists('GridFieldExportReportButton')) {

	class GridFieldExportReportButton extends GridFieldExportButton {

		/**
		 * Generate export fields for CSV.
		 *
		 * Replaces the definition in GridFieldExportButton, this is the same as original except
		 * it sources the {@link List} from $gridField->getList() instead of $gridField->getManipulatedList()
	 	 *
		 * @param GridField $gridField
		 * @return array
		 */
		public function generateExportFileData($gridField) {
			$separator = $this->csvSeparator;
			$csvColumns = ($this->exportColumns)
				? $this->exportColumns
				: singleton($gridField->getModelClass())->summaryFields();
			$fileData = '';
			$columnData = array();
			$fieldItems = new ArrayList();

			if($this->csvHasHeader) {
				$headers = array();

				// determine the CSV headers. If a field is callable (e.g. anonymous function) then use the
				// source name as the header instead
				foreach($csvColumns as $columnSource => $columnHeader) {
					$headers[] = (!is_string($columnHeader) && is_callable($columnHeader)) ? $columnSource : $columnHeader;
				}

				$fileData .= "\"" . implode("\"{$separator}\"", array_values($headers)) . "\"";
				$fileData .= "\n";
			}

			// The is the only variation from the parent, using getList() instead of getManipulatedList()
			$items = $gridField->getList();

			// @todo should GridFieldComponents change behaviour based on whether others are available in the config?
			foreach($gridField->getConfig()->getComponents() as $component){
				if($component instanceof GridFieldFilterHeader || $component instanceof GridFieldSortableHeader) {
					$items = $component->getManipulatedData($gridField, $items);
				}
			}

			foreach($items->limit(null) as $item) {
				$columnData = array();

				foreach($csvColumns as $columnSource => $columnHeader) {
					if(!is_string($columnHeader) && is_callable($columnHeader)) {
						if($item->hasMethod($columnSource)) {
							$relObj = $item->{$columnSource}();
						} else {
							$relObj = $item->relObject($columnSource);
						}

						$value = $columnHeader($relObj);
					} else {
						$value = $gridField->getDataFieldValue($item, $columnSource);
					}

					$value = str_replace(array("\r", "\n"), "\n", $value);
					$columnData[] = '"' . str_replace('"', '\"', $value) . '"';
				}
				$fileData .= implode($separator, $columnData);
				$fileData .= "\n";

				$item->destroy();
			}

			return $fileData;
		}
	} // end class GridFieldExportReportButton
}

/**
 * An extension to GridFieldPrintButton to support printing custom Reports
 *
 * This code was adapted from a solution posted in SilverStripe.org forums:
 * http://www.silverstripe.org/customising-the-cms/show/38202
 *
 * @package cms
 * @subpackage content
 * @author Michael Armstrong <http://www.silverstripe.org/ForumMemberProfile/show/30887>
 * @author Michael Parkhill <mike@silverstripe.com>
 */
if (!class_exists('GridFieldPrintReportButton')) {
	class GridFieldPrintReportButton extends GridFieldPrintButton {

		/**
		 * Export core
		 *
		 * Replaces definition in GridFieldPrintButton
		 * same as original except sources data from $gridField->getList() instead of $gridField->getManipulatedList()
 		 *
		 * @param GridField
		 */
		public function generatePrintData(GridField $gridField) {
			$printColumns = $this->getPrintColumnsForGridField($gridField);
			$header = null;

			if($this->printHasHeader) {
				$header = new ArrayList();
				foreach($printColumns as $field => $label){
					$header->push(new ArrayData(array(
						"CellString" => $label,
					)));
				}
			}

			// The is the only variation from the parent class, using getList() instead of getManipulatedList()
			$items = $gridField->getList();

			$itemRows = new ArrayList();

			foreach($items as $item) {
				$itemRow = new ArrayList();

				foreach($printColumns as $field => $label) {
					$value = $gridField->getDataFieldValue($item, $field);
					$itemRow->push(new ArrayData(array(
						"CellString" => $value,
					)));
				}

				$itemRows->push(new ArrayData(array(
					"ItemRow" => $itemRow
				)));

				$item->destroy();
			}

			$ret = new ArrayData(array(
				"Title" => $this->getTitle($gridField),
				"Header" => $header,
				"ItemRows" => $itemRows,
				"Datetime" => SS_Datetime::now(),
				"Member" => Member::currentUser(),
			));

			return $ret;
		}
	} // end class GridFieldPrintReportButton
}
