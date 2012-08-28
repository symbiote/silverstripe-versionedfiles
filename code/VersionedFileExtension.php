<?php
/**
 * An extension that adds the ability to replace files with new uploads, and
 * view and roll back to existing versions.
 *
 * @package silverstripe-versionedfiles
 */
class VersionedFileExtension extends DataExtension {

	public static $has_one = array('CurrentVersion' => 'FileVersion');
	public static $has_many = array('Versions'      => 'FileVersion');

	/**
	 * @param FieldSet $fields
	 */
	public function updateCMSFields(FieldList $fields) {
		if($this->owner instanceof Folder || !$this->owner->ID) return;

		$fields->addFieldToTab('Root.Main', new ReadonlyField(
			'VersionNumber',
			_t('VersionedFiles.CURRENTVERSION', 'Current Version')
		), 'Created');


		// History

		$gridFieldConfig = GridFieldConfig::create()->addComponents(
			new GridFieldToolbarHeader(),
			//new GridFieldFilterHeader(),
			new GridFieldSortableHeader(),
			new GridFieldDataColumns(),
			new GridFieldPaginator(15),
			new GridFieldViewButton(),
			//new GridFieldDeleteAction(),
			new GridFieldDetailForm()
		);

		$gridField = new GridField('Versions', 'Versions', $this->owner->Versions(), $gridFieldConfig);
		$columns = $gridField->getConfig()->getComponentByType('GridFieldDataColumns');
		$columns->setDisplayFields(array(
			'VersionNumber' => _t('VersionedFiles.VERSIONNUMBER', 'Version Number'),
			'Creator.Name'  => _t('VersionedFiles.CREATOR', 'Creator'),
			'Created'       => _t('VersionedFiles.DATE', 'Date'),
			'Link'          => _t('VersionedFiles.LINK', 'Link'),
			'IsCurrent'     => _t('VersionedFiles.ISCURRENT', 'Is Current')
		));
		
		$columns->setFieldCasting(array(
			'Created' => 'Date->Nice'
		));

		$columns->setFieldFormatting(array (
			'Link'      => '<a href=\"$URL\" target=\"_blank\">$Name</a>',
			'IsCurrent' => '{$IsCurrent()->Nice()}',
			'Created'   => '{$obj(\'Created\')->Nice()}'
		));

		// history

		$versions = $this->owner->Versions (
			sprintf('"VersionNumber" <> %d', $this->getVersionNumber())
		);

		if($versions && $versions->Count() && $this->owner->canEdit()) {
			$fields->addFieldToTab('Root.History', new HeaderField('RollbackHeader', _t('VersionedFiles.ROLLBACKPREVVERSION', 'Rollback to a Previous Version')));
			$fields->addFieldToTab('Root.History', new DropdownField (
				'PreviousVersion',
				'',
				$versions->map('VersionNumber'),
				null,
				null,
				_t('VersionedFiles.SELECTAVERSION', '(Select a Version)')	
			));
		}

		$fields->addFieldToTab('Root.History', $gridField);

		// Replace

		if(!$this->owner->canEdit()) return;

		$folder = $this->owner->Parent();
		$uploadField = VersionedFileUploadField::create('ReplacementFile', '');
		$uploadField->setConfig('previewMaxWidth', 40);
		$uploadField->setConfig('previewMaxHeight', 30);
		$uploadField->setConfig('maxNumberOfFiles', 1);
		$uploadField->addExtraClass('ss-assetuploadfield');
		$uploadField->removeExtraClass('ss-uploadfield');
		$uploadField->setTemplate('VersionedFileUploadField');
		$uploadField->currentVersionFile = $this->owner; 
		$uploadField->relationAutoSetting = false;

		if ($folder->exists() && $folder->getFilename()) {
			$path = preg_replace('/^' . ASSETS_DIR . '\//', '', $folder->getFilename());
			$uploadField->setFolderName($path);
		} else {
			$uploadField->setFolderName(ASSETS_DIR);
		}

		// set the valid extensions to only that of the original file
		$ext = strtolower($this->owner->Extension);
		$uploadField->getValidator()->setAllowedExtensions(array($ext));

		// css / js requirements for asset admin style file uploader
		Requirements::javascript(FRAMEWORK_DIR . '/javascript/AssetUploadField.js');
		Requirements::css(FRAMEWORK_DIR . '/css/AssetUploadField.css');

		$fields->addFieldToTab('Root.Replace', $uploadField);


		$sameTypeMessage = sprintf(_t(
			'VersionedFiles.SAMETYPEMESSAGE',
			'You may only replace this file with another of the same type: .%s'
		), $this->owner->getExtension());

		$fields->addFieldToTab('Root.Replace', new LiteralField('SameTypeMessage', "<p>$sameTypeMessage</p>"));

		return;		
	}

	/**
	 * Creates the initial version when the file is created, as well as updating
	 * the version records when the parent file is moved.
	 */
	public function onAfterWrite() {
		$changed = $this->owner->getChangedFields(true, 2);

		if(!$this->owner instanceof Folder && !$this->owner->CurrentVersionID) {
			$this->createVersion();
		}

		if (array_key_exists('Filename', $changed)) {
			if($changed['Filename']['before'] == null){
				return;
			}

			$oldDirname = '/' . trim(dirname($changed['Filename']['before']), '/');
			$newDirname = '/' . trim(dirname($changed['Filename']['after']), '/');

			if ($oldDirname == $newDirname) return;

			// First move the _versions directory across.
			$versionsDir = FileVersion::VERSION_FOLDER . '/' . $this->owner->ID;
			$oldVersions = BASE_PATH . $oldDirname . '/' . $versionsDir;
			$newVersions = BASE_PATH . $newDirname . '/' . $versionsDir;

			if (is_dir($oldVersions)) {
				if (!is_dir($newVersions)) {
					mkdir($newVersions, Filesystem::$folder_create_mask, true);
				}

				rename($oldVersions, $newVersions);
			}

			// Then update individual version records to point to the new
			// location.
			foreach ($this->owner->Versions() as $version) {
				if (strpos($version->Filename, $oldDirname) === 0) {
					$version->Filename = $newDirname . substr($version->Filename, strlen($oldDirname));
					$version->write();
				}
			}
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
		if(!is_numeric($version)) return;

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