<?php
/**
 * An extension to automatically regenerate all cached/resampled images when an
 * image version is changed.
 *
 * @package silverstripe-versionedfiles
 */
class VersionedImageExtension extends DataExtension
{
    /**
     * Regenerates all cached images if the version number has been changed.
     */
    public function onBeforeWrite()
    {
        if (!$this->owner->isChanged('CurrentVersionID')) {
            return;
        }

        // Support new {@see Image::regenerateFormattedImages} method in 3.2
        if ($this->owner->hasMethod('regenerateFormattedImages')) {
            $this->owner->regenerateFormattedImages();
            return;
        }

        if ($this->owner->ParentID) {
            $base = $this->owner->Parent()->getFullPath();
        } else {
            $base = ASSETS_PATH . '/';
        }

        $resampled = "{$base}_resampled";
        if (!is_dir($resampled)) {
            return;
        }

        $files    = scandir($resampled);
        $iterator = new ArrayIterator($files);
        $filter   = new RegexIterator(
            $iterator,
            sprintf(
                "/([a-z]+)([0-9]?[0-9a-f]*)-%s/i",
                preg_quote($this->owner->Name)
            ),
            RegexIterator::GET_MATCH
        );

        // grab each resampled image and regenerate it
        foreach ($filter as $cachedImage) {
            $path      = "$resampled/{$cachedImage[0]}";

            //skip resampled image files that don't exist
            if (!file_exists($path)) {
                continue;
            }

            $size      = getimagesize($path);
            $method    = $cachedImage[1];
            $arguments = $cachedImage[2];

            unlink($path);

            // Determine the arguments used to generate an image, and regenerate
            // it. Different methods need different ways of determining the
            // original arguments used.
            switch (strtolower($method)) {
                case 'paddedimage':
                    $color = preg_replace("/^{$size[0]}{$size[1]}/", '', $arguments);
                    $this->owner->$method($size[0], $size[1], $color);
                    break;
                case 'resizedimage':
                case 'setsize':
                case 'croppedimage':
                case 'setratiosize':
                case 'croppedfocusedimage':
                    $this->owner->$method($size[0], $size[1]);
                    break;

                case 'setwidth':
                    $this->owner->$method($size[0]);
                    break;

                case 'setheight':
                    $this->owner->$method($size[1]);
                    break;

                default:
                    $this->owner->$method($arguments);
                    break;
            }
        }
    }
}
