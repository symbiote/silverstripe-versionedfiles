<?php
/**
 * This class represents a single version of a file. Each file can have many versions, and one of these is currently
 * active at any one time.
 *
 * @package silverstripe-versionedfiles
 */
class FileVersion extends DataObject {

	public static $db = array (
		'VersionNumber' => 'Int',
		'Filename'      => 'Varchar(255)'
	);

	public static $has_one = array (
		'Creator' => 'Member',
		'File'    => 'File'
	);

	/**
	 * Saves version meta-data, and generates the saved file version on first write.
	 */
	public function onBeforeWrite() {
		if(!$this->isInDB()) {
			$this->CreatorID = Member::currentUserID();
		}

		if(!$this->VersionNumber) {
			$versions = DataObject::get('FileVersion', sprintf('"FileID" = %d', $this->FileID));

			if($versions) {
				$this->VersionNumber = $versions->Count() + 1;
			} else {
				$this->VersionNumber = 1;
			}
		}

		if(!$this->Filename) {
			$this->Filename = $this->saveCurrentVersion();
		}

		parent::onBeforeWrite();
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return "Version $this->VersionNumber";
	}

	/**
	 * @return string
	 */
	public function getName() {
		return basename($this->Filename);
	}

	/**
	 * @return string
	 */
	public function getURL() {
		return Controller::join_links(Director::baseURL(), $this->Filename);
	}

	/**
	 * @return Boolean
	 */
	public function IsCurrent() {
		return DBField::create('Boolean', ($this->File()->CurrentVersionID == $this->ID));
	}

	/**
	 * Saves the current version of the linked File object in a _versions directory, then returns the relative path
	 * to where it is stored.
	 *
	 * @return string
	 */
	protected function saveCurrentVersion() {
		if($this->File()->ParentID) {
			$base = Controller::join_links (
				$this->File()->Parent()->getFullPath(), '_versions', $this->FileID
			);
		} else {
			$base = Controller::join_links (
				Director::baseFolder(), ASSETS_DIR, '_versions', $this->FileID
			);
		}

		Filesystem::makeFolder($base);

		$extension = $this->File()->getExtension();
		$basename  = basename($this->File()->Name, $extension);
		$version   = $this->VersionNumber;

		$cachedPath = Controller::join_links($base, "{$basename}$version.$extension");

		if(!copy($this->File()->getFullPath(), $cachedPath)) {
			throw new Exception("Unable to save version #$version of file #$this->FileID.");
		}

		return Director::makeRelative($cachedPath);
	}

}