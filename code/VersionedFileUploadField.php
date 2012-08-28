<?php
class VersionedFileUploadField extends UploadField {


	/**
	 * The file to upload a new version of
	 * @var File
	 */
	public $currentVersionFile;


	/**
	 * Action to handle upload of a single file
	 * 
	 * @param SS_HTTPRequest $request
	 * @return string json
	 */
	public function upload(SS_HTTPRequest $request) {

		if(!$this->currentVersionFile){
			return;
		}


		if($this->isDisabled() || $this->isReadonly()) return $this->httpError(403);

		// Protect against CSRF on destructive action
		$token = $this->getForm()->getSecurityToken();
		if(!$token->checkRequest($request)) return $this->httpError(400);

		$name = $this->getName();
		$tmpfile = $request->postVar($name);
		$record = $this->getRecord();
		
		// Check if the file has been uploaded into the temporary storage.
		if (!$tmpfile) {
			$return = array('error' => _t('UploadField.FIELDNOTSET', 'File information not found'));
		} else {
			$return = array(
				'name' => $tmpfile['name'],
				'size' => $tmpfile['size'],
				'type' => $tmpfile['type'],
				'error' => $tmpfile['error']
			);
		}

		// Check for constraints on the record to which the file will be attached.
		if (!$return['error'] && $this->relationAutoSetting && $record && $record->exists()) {
			$tooManyFiles = false;
			// Some relationships allow many files to be attached.
			if ($this->getConfig('allowedMaxFileNumber') && ($record->has_many($name) || $record->many_many($name))) {
				if(!$record->isInDB()) $record->write();
				$tooManyFiles = $record->{$name}()->count() >= $this->getConfig('allowedMaxFileNumber');
			// has_one only allows one file at any given time.
			} elseif($record->has_one($name)) {
				$tooManyFiles = $record->{$name}() && $record->{$name}()->exists();
			}

			// Report the constraint violation.
			if ($tooManyFiles) {
				if(!$this->getConfig('allowedMaxFileNumber')) $this->setConfig('allowedMaxFileNumber', 1);
				$return['error'] = _t(
					'UploadField.MAXNUMBEROFFILES', 
					'Max number of {count} file(s) exceeded.',
					array('count' => $this->getConfig('allowedMaxFileNumber'))
				);
			}
		}

		// Process the uploaded file
		if (!$return['error']) {
			// Get the uploaded file into a new file object.
			try {
				$currentFilePath = $this->currentVersionFile->getFullPath();
				if(file_exists($currentFilePath)){
					unlink($this->currentVersionFile->getFullPath());
				}
				$this->upload->loadIntoFile(array_merge($tmpfile, array('name' => $this->currentVersionFile->Name)), $this->currentVersionFile, $this->folderName);
			} catch (Exception $e) {
				// we shouldn't get an error here, but just in case
				$return['error'] = $e->getMessage();
			}

			if (!$return['error']) {
				if ($this->upload->isError()) {
					$return['error'] = implode(' '.PHP_EOL, $this->upload->getErrors());
				} else {
					$file = $this->upload->getFile();

					$file->createVersion();

					// Attach the file to the related record.
					if ($this->relationAutoSetting) {
						$this->attachFile($file);
					}

					// Collect all output data.
					$file =  $this->customiseFile($file);
					$return = array_merge($return, array(
						'id' => $file->ID,
						'name' => $file->getTitle() . '.' . $file->getExtension(),
						'url' => $file->getURL(),
						'thumbnail_url' => $file->UploadFieldThumbnailURL,
						'edit_url' => $file->UploadFieldEditLink,
						'size' => $file->getAbsoluteSize(),
						'buttons' => $file->UploadFieldFileButtons
					));
				}
			}
		}
		$response = new SS_HTTPResponse(Convert::raw2json(array($return)));
		$response->addHeader('Content-Type', 'text/plain');
		return $response;
	}
}
