<?php
/**
 * An extension that adds the ability to replace files.
 *
 * @package silverstripe-versionedfiles
 */
class VersionedFileExtension extends DataObjectDecorator {

	/**
	 * @return array
	 */
	public function extraStatics() {
		return array (
			'has_one'  => array('CurrentVersion' => 'FileVersion'),
			'has_many' => array('Versions'       => 'FileVersion')
		);
	}

	/**
	 * @param FieldSet $fields
	 */
	public function updateCMSFields($fields) {
		if($this->owner instanceof Folder) return;

		$fields->addFieldToTab (
			'BottomRoot.Main',
			new ReadonlyField('VersionNumber', _t('VersionedFiles.CURRENTVERSION', 'Current Version')),
			'Created'
		);

		$fields->addFieldToTab('BottomRoot.History', $versions = new TableListField (
			'Versions',
			'FileVersion',
			array (
				'VersionNumber' => _t('VersionedFiles.VERSIONNUMBER', 'Version Number'),
				'Creator.Name'  => _t('VersionedFiles.CREATOR', 'Creator'),
				'Created'       => _t('VersionedFiles.DATE', 'Date'),
				'Link'          => _t('VersionedFiles.LINK', 'Link'),
				'IsCurrent'     => _t('VersionedFiles.ISCURRENT', 'Is Current')
			),
			'"FileID" = ' . $this->owner->ID,
			'"VersionNumber" DESC'
		));
		$fields->fieldByName('BottomRoot.History')->setTitle(_t('VersionedFiles.HISTORY', 'History'));

		$versions->setFieldFormatting(array (
			'Link'      => '<a href=\"$URL\">$Name</a>',
			'IsCurrent' => '{$IsCurrent()->Nice()}',
			'Created'   => '{$obj(\'Created\')->Nice()}'
		));
		$versions->disableSorting();
		$versions->setPermissions(array());

		if(!$this->owner->canEdit()) return;

		$uploadMsg   = _t('VersionedFiles.UPLOADNEWFILE', 'Upload a New File');
		$rollbackMsg = _t('VersionedFiles.ROLLBACKPREVVERSION', 'Rollback to a Previous Version');

		$replacementOptions = array("upload//$uploadMsg" => new FileField (
			'ReplacementFile', _t('VersionedFiles.SELECTREPLACEMENTFILE', 'Select a Replacement File')
		));

		$versions = $this->owner->Versions (
			sprintf('"VersionNumber" <> %d', $this->getVersionNumber())
		);

		if($versions && $versions->Count()) {
			$replacementOptions["rollback//$rollbackMsg"] = new DropdownField (
				'PreviousVersion',
				_t('VersionedFiles.SELECTPREVVERSION', 'Select a Previous Version'),
				$versions->map('VersionNumber'),
				null,
				null,
				_t('VersionedFiles.SELECTAVERSION', '(Select a Version)')
			);
		}

		$fields->addFieldToTab (
			'BottomRoot.Replace', new SelectionGroup('Replace', $replacementOptions)
		);
		$fields->fieldByName('BottomRoot.Replace')->setTitle(_t('VersionedFiles.REPLACE', 'Replace'));
	}

	/**
	 * Creates the initial version when the file is created.
	 */
	public function onAfterWrite() {
		if(!$this->owner instanceof Folder && !$this->owner->CurrentVersionID) $this->createVersion();
	}

	/**
	 * Since AssetAdmin does not use {@link onBeforeWrite}, onAfterUpload is also needed.
	 */
	public function onAfterUpload() {
		$this->onAfterWrite();
	}

	/**
	 * Get the current file version number, if one is available.
	 *
	 * @return int|null
	 */
	public function getVersionNumber() {
		if($this->owner->CurrentVersionID) return $this->owner->CurrentVersion()->VersionNumber;
	}

	/**
	 * Handles rolling back to a selected version on save.
	 *
	 * @param int $version
	 */
	public function savePreviousVersion($version) {
		if(Controller::curr()->getRequest()->requestVar('Replace') != 'rollback' || !is_numeric($version)) return;

		$fileVersion = DataObject::get_one (
			'FileVersion',
			sprintf('"FileID" = %d AND "VersionNumber" = %d', $this->owner->ID, $version)
		);

		if(!$fileVersion) return;

		$versionPath = Controller::join_links(Director::baseFolder(), $fileVersion->Filename);
		$currentPath = $this->owner->getFullPath();

		if(!copy($versionPath, $currentPath)) {
			throw new Exception("Could not replace file #{$this->owner->ID} with version #$version.");
		}

		$this->owner->CurrentVersionID = $fileVersion->ID;
		$this->owner->write();
	}

	/**
	 * Called by the edit form upon save, and handles replacing the file if a replacement is specified.
	 *
	 * @param array $tmpFile
	 */
	public function saveReplacementFile(array $tmpFile) {
		if(Controller::curr()->getRequest()->requestVar('Replace') != 'upload' || $tmpFile['error'] !=  UPLOAD_ERR_OK) return;

		$upload  = new Upload();
		$tmpFile = array_merge($tmpFile, array('name' => $this->owner->Name));
		$folder  = null;

		if($this->owner->ParentID) {
			$folder = substr($this->owner->Parent()->getRelativePath(), strlen(ASSETS_DIR) + 1, -1);
		}

		if(!$upload->validate($tmpFile)) {
			throw new Exception (
				"Could not replace file $file->ID: " . implode(', ', $upload->getErrors())
			);
		}

		// the file must be removed to prevent the upload being renamed
		unlink($this->owner->getFullPath());
		$upload->loadIntoFile($tmpFile, $this->owner, $folder);

		$this->createVersion();
	}

	/**
	 * Creates a new file version and sets it as the current version.
	 *
	 * @param bool $write
	 */
	public function createVersion() {
		if(!file_exists($this->owner->getFullPath())) return;

		$version = new FileVersion();
		$version->FileID = $this->owner->ID;
		$version->write();

		$this->owner->CurrentVersionID = $version->ID;
		$this->owner->write();
	}

}