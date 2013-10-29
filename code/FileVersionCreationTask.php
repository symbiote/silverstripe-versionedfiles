<?php
/**
 * Creates initial version records for File objects that do not have any
 * versioning information. This should be run when the module is first installed
 * in order to bootstrap the version history.
 *
 * @package silverstripe-versionedfiles
 */
class FileVersionCreationTask extends BuildTask {

	public function getTitle() {
		return _t(
			'FileVersionCreationTask.Title',
			'File Version Creation Task'
		);
	}

	public function getDescription() {
		return _t(
			'FileVersionCreationTask.Desc',
			'Creates version records for files that do not have one.'
		);
	}

	/**
	 * @param HTTPRequest $request
	 */
	public function run($request) {
		$versionless = DataObject::get(
			'File',
			'"File"."ClassName" <> \'Folder\' AND "FileVersion"."FileID" IS NULL')
				->leftJoin("FileVersion",'"FileVersion"."FileID" = "File"."ID"');

		if($versionless) foreach($versionless as $file) {
			$file->createVersion();
		}

		if($versionless) {
			echo _t(
				'FileVersionCreationTask.Created',
				"Created {count} file version records.",
				array('count' => $versionless->Count())
			);
		} else {
			echo _t(
				'FileVersionCreationTask.NoCreated',
				'No file version records created.'
			);
		}
	}

}