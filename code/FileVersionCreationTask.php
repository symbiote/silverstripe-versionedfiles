<?php
/**
 * Creates initial version records for File objects that do not have any
 * versioning information. This should be run when the module is first installed
 * in order to bootstrap the version history.
 *
 * @package silverstripe-versionedfiles
 */
class FileVersionCreationTask extends BuildTask {

	protected $title = 'File Version Creation Task';

	protected $description = 'Creates version records for files that do not have one.';

	/**
	 * @param HTTPRequest $request
	 */
	public function run($request) {
		$versionless = DataObject::get(
			'File',
			'"File"."ClassName" <> \'Folder\' AND "FileVersion"."FileID" IS NULL',
			null,
			'LEFT JOIN "FileVersion" ON "FileVersion"."FileID" = "File"."ID"'
		);

		if($versionless) foreach($versionless as $file) {
			$file->createVersion();
		}

		if($versionless) {
			echo "Created {$versionless->Count()} file version records.";
		} else {
			echo 'No file version records created.';
		}
	}

}