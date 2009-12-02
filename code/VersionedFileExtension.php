<?php
/**
 * An extension that adds the ability to replace files.
 *
 * @package versionedfiles
 */
class VersionedFileExtension extends DataObjectDecorator {

	/**
	 * @return array
	 */
	public function extraStatics() {
		return array('has_many' => array('Versions' => 'FileVersion'));
	}

	/**
	 * @param FieldSet $fields
	 */
	public function updateCMSFields($fields) {
		if($this->owner instanceof Folder || !$this->owner->canEdit()) return;

		$fields->addFieldToTab (
			'BottomRoot.Replace',
			new FileField('ReplacementFile', 'Select a Replacement File')
		);
	}

	/**
	 * Called by the edit form upon save, and handles replacing the file if a replacement is specified.
	 *
	 * @param array $tmpFile
	 */
	public function saveReplacementFile(array $tmpFile) {
		if($tmpFile['error'] !=  UPLOAD_ERR_OK) return;

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

		// save versioning information
		$version = new FileVersion();
		$version->FileID = $this->owner->ID;
		$version->write();
	}

}