<?php
/**
 * An extension that adds the ability to replace files with new uploads, and
 * view and roll back to existing versions.
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
		if($this->owner instanceof Folder || !$this->owner->ID) return;

		$fields->addFieldToTab('BottomRoot.Main', new ReadonlyField(
			'VersionNumber',
			_t('VersionedFiles.CURRENTVERSION', 'Current Version')
		), 'Created');

		$fields->addFieldToTab('BottomRoot.History', $versions = new TableListField(
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

		$history = $fields->fieldByName('BottomRoot.History');
		$history->setTitle(_t('VersionedFiles.HISTORY', 'History'));

		$versions->setFieldFormatting(array (
			'Link'      => '<a href=\"$URL\">$Name</a>',
			'IsCurrent' => '{$IsCurrent()->Nice()}',
			'Created'   => '{$obj(\'Created\')->Nice()}'
		));
		$versions->disableSorting();
		$versions->setPermissions(array());

		if(!$this->owner->canEdit()) return;

		$uploadMsg   = _t('VersionedFiles.UPLOADNEWFILE', 'Upload a New File');
		$rollbackMsg = _t(
			'VersionedFiles.ROLLBACKPREVVERSION',
			'Rollback to a Previous Version'
		);

		$sameTypeMessage = sprintf(_t(
			'VersionedFiles.SAMETYPEMESSAGE',
			'You may only replace this file with another of the same type: .%s'
		), $this->owner->getExtension());

		$replacementOptions = array("upload//$uploadMsg" => new FieldGroup (
			new LiteralField('SameTypeMessage', '<p>' . $sameTypeMessage . '</p>'),
			new FileField('ReplacementFile', '')
		));

		$versions = $this->owner->Versions (
			sprintf('"VersionNumber" <> %d', $this->getVersionNumber())
		);

		if($versions && $versions->Count()) {
			$replacementOptions["rollback//$rollbackMsg"] = new DropdownField (
				'PreviousVersion',
				'',
				$versions->map('VersionNumber'),
				null,
				null,
				_t('VersionedFiles.SELECTAVERSION', '(Select a Version)')
			);
		}

		$fields->addFieldToTab (
			'BottomRoot.Replace', new SelectionGroup('Replace', $replacementOptions)
		);

		$replace = $fields->fieldByName('BottomRoot.Replace');
		$replace->setTitle(_t('VersionedFiles.REPLACE', 'Replace'));
	}

	/**
	 * Creates the initial version when the file is created.
	 */
	public function onAfterWrite() {
		if(!$this->owner instanceof Folder && !$this->owner->CurrentVersionID) {
			$this->createVersion();
		}
	}

	/**
	 * Since AssetAdmin does not use {@link onBeforeWrite}, onAfterUpload is
	 * also needed.
	 *
	 * @uses onAfterWrite()
	 */
	public function onAfterUpload() {
		$this->onAfterWrite();
	}

	/**
	 * Deletes all saved version of the file as well as the file itself.
	 */
	public function onBeforeDelete() {
		$currentVersion = $this->owner->CurrentVersion();

		// make sure there actually is a current version, otherwise we're going
		// to end up deleting a bunch of incorrect stuff!
		if ($currentVersion && $currentVersion->ID > 0) {
			$folder = dirname($this->owner->CurrentVersion()->getFullPath());

			if($versions = $this->owner->Versions()) {
				foreach($versions as $version) {
					$version->delete();
				}
			}

			if(is_dir($folder)) Filesystem::removeFolder($folder);
		}
	}

	/**
	 * @return int
	 */
	public function getVersionNumber() {
		if($this->owner->CurrentVersionID) return $this->owner->CurrentVersion()->VersionNumber;
	}

	/**
	 * @param int $version
	 */
	public function setVersionNumber($version) {
		$fileVersion = DataObject::get_one('FileVersion', sprintf(
			'"FileID" = %d AND "VersionNumber" = %d', $this->owner->ID, $version
		));

		if(!$fileVersion) {
			throw new Exception(
				"Could not get version #$version of file #{$this->owner->ID}"
			);
		}

		$versionPath = Controller::join_links(
			Director::baseFolder(), $fileVersion->Filename
		);
		$currentPath = $this->owner->getFullPath();

		if(!copy($versionPath, $currentPath)) {
			throw new Exception(
				"Could not replace file #{$this->owner->ID} with version #$version."
			);
		}

		$this->owner->CurrentVersionID = $fileVersion->ID;
	}

	/**
	 * Handles rolling back to a selected version on save.
	 *
	 * @param int $version
	 */
	public function savePreviousVersion($version) {
		if(Controller::curr()->getRequest()->requestVar('Replace') != 'rollback' || !is_numeric($version)) return;

		try {
			$this->setVersionNumber($version);
			$this->owner->write();
		} catch(Exception $e) {
			throw new ValidationException(new ValidationResult (
				false,
				"Could not replace file #{$this->owner->ID} with version #$version."
			));
		}
	}

	/**
	 * Called by the edit form upon save, and handles replacing the file if a
	 * replacement is specified.
	 *
	 * @param array $tmpFile
	 */
	public function saveReplacementFile(array $tmpFile) {
		if(Controller::curr()->getRequest()->requestVar('Replace') != 'upload' || $tmpFile['error'] !=  UPLOAD_ERR_OK) return;

		$upload  = new Upload();
		$folder  = null;

		if($this->owner->ParentID) {
			$folder = substr(
				$this->owner->Parent()->getRelativePath(),
				strlen(ASSETS_DIR) + 1,
				-1
			);
		}

		$upload->getValidator()->setAllowedExtensions(array($this->owner->getExtension()));

		if(!$upload->validate($tmpFile)) {
			$errors = implode(', ', $upload->getErrors());

			throw new ValidationException(new ValidationResult (
				false,
				"Could not replace '{$this->owner->Name}': " . $errors
			));
		}

		// the file must be removed to prevent the upload being renamed
		unlink($this->owner->getFullPath());

		$upload->loadIntoFile (
			array_merge($tmpFile, array('name' => $this->owner->Name)),
			$this->owner,
			$folder
		);

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