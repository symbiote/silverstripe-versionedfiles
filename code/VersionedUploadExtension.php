<?php
/**
 * @package silverstripe-versionedfiles
 */
class VersionedUploadExtension extends Extension
{
    /**
     * There is some duplication in the Upload: replaceFile functionality and the versionedFiles replace
     * functionality. With the CMS replace function the versionedFiles replace does't work and vice-versa.
     * This extension detects if the replaceFile config setting is turned on and hooks in to add create
     * a new version on each upload from the CMS, if that is the case.
     * @param $file File - the uploaded File object
     */
    public function onAfterLoad($file)
    {
        if ($this->owner->getReplaceFile()) {
            $file->createVersion();
        }
    }
}
