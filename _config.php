<?php
/**
 * @package silverstripe-versionedfiles
 */

Object::add_extension('File',   'VersionedFileExtension');
Object::add_extension('Image',  'VersionedImageExtension');
Object::add_extension('Folder', 'VersionedFolderExtension');