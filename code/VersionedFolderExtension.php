<?php
/**
 * @package silverstripe-versionedfiles
 */
class VersionedFolderExtension extends DataExtension {

	public function onBeforeDelete() {
		// A workaround for that fact that Folder::onBeforeDelete() only removes
		// the folder if only a _resampled dir exists. If the folder is empty
		// and only has _resampled and _versions directories then delete it.
		if ($this->owner->Filename && is_dir($this->owner->getFullPath())) {
			$items       = scandir($this->owner->getFullPath());
			$versionsDir = FileVersion::VERSION_FOLDER;

			// Remove . and .. items.
			array_shift($items);
			array_shift($items);

			$delete = (
				count($items) == 1 && $items[0] == $versionsDir
				|| count($items) == 2 && $items == array('_resampled', $versionsDir)
			);

			if ($delete) {
				Filesystem::removeFolder($this->owner->getFullPath());
			}
		}
	}

}