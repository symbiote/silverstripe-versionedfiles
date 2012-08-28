<?php
/**
 * An extension to automatically regenerate all cached/resampled images when an
 * image version is changed.
 *
 * @package silverstripe-versionedfiles
 */
class VersionedImageExtension extends DataExtension {

	/**
	 * Regenerates all cached images if the version number has been changed.
	 */
	public function onBeforeWrite() {
		if(!$this->owner->isChanged('CurrentVersionID')) return;

		if($this->owner->ParentID) {
			$base = $this->owner->Parent()->getFullPath();
		} else {
			$base = ASSETS_PATH . '/';
		}

		if(!is_dir($resampled = "{$base}_resampled")) return;

		$files    = scandir($resampled);
		$iterator = new ArrayIterator($files);
		$filter   = new RegexIterator (
			$iterator,
			sprintf(
				"/([a-zA-Z]+)([0-9]*)-%s/",
				preg_quote($this->owner->Name)
			),
			RegexIterator::GET_MATCH
		);

		// grab each resampled image and regenerate it
		foreach($filter as $cachedImage) {
			$path      = "$resampled/{$cachedImage[0]}";
			$size      = getimagesize($path);
			$method    = $cachedImage[1];
			$arguments = $cachedImage[2];

			unlink($path);

			// Determine the arguments used to generate an image, and regenerate
			// it. Different methods need different ways of determining the
			// original arguments used.
			switch(strtolower($method)) {
				case 'resizedimage':
				case 'setsize':
				case 'paddedimage':
				case 'croppedimage':
					$this->owner->$method($size[0], $size[1]);
					break;

				case 'setwidth':
					$this->owner->$method($size[0]);
					break;

				case 'setheight':
					$this->owner->$method($size[1]);
					break;

				case 'setratiosize':
					if(strpos($arguments, $size[0]) === 0) {
						$this->owner->$method(
							$size[0], substr($arguments, strlen($size[0]))
						);
					} else {
						$this->owner->$method(
							$size[1], substr($arguments, 0, strlen($size[0]) * -1)
						);
					}
					break;

				default:
					$this->owner->$method($arguments);
					break;
			}
		}
	}

}