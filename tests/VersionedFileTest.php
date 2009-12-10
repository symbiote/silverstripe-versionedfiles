<?php
/**
 * @package    silverstripe-versionedfiles
 * @subpackage tests
 */
class VersionedFileTest extends FunctionalTest {

	protected $usesDatabase = true;

	protected $folder;

	protected $file;

	public function setUp() {
		parent::setUp();

		$this->folder = new Folder();
		$this->folder->Filename = ASSETS_DIR . '/versionedfiles-test';
		$this->folder->write();

		$file = $this->folder->getFullPath() . 'test-file.txt';
		file_put_contents($file, md5('first-version'));

		$this->file = new File();
		$this->file->ParentID = $this->folder->ID;
		$this->file->Filename = $this->folder->getFilename() . 'test-file.txt';
		$this->file->write();
	}

	public function tearDown() {
		parent::tearDown();

		Filesystem::removeFolder($this->folder->getFullPath());
		$this->folder->delete();
	}

	public function testInitialSaveCreatesVersion() {
		$this->assertNull($this->folder->CurrentVersionID, 'Folders do not have versions created.');

		$this->assertEquals(1, $this->file->getVersionNumber(), 'Files have initial versions vreated.');
		$this->assertEquals (
			md5('first-version'),
			file_get_contents($this->file->CurrentVersion()->getFullPath()),
			'Files are copied to a stored version directory.'
		);
	}
	public function testUploadReplacesFile() {
		$this->markTestIncomplete('ComplexTableField is currently not testable.');

		$this->logInWithPermssion('ADMIN');
		$this->getFileEditForm();
	}

	public function testUploadCreatesVersion() {
		$this->markTestIncomplete('ComplexTableField is currently not testable.');

		$this->logInWithPermssion('ADMIN');
		$this->getFileEditForm();
	}

	public function testRollbackReplacesFile() {
		$this->markTestIncomplete('ComplexTableField is currently not testable.');

		$this->logInWithPermssion('ADMIN');
		$this->getFileEditForm();
	}

	protected function getFileEditForm() {
		ob_start();

		$admin  = new AssetAdmin();
		$folder = Controller::join_links($admin->Link(), 'show', $this->folder->ID);
		$file   = Controller::join_links($admin->Link(), 'EditForm/field/Files/item', $this->file->ID, 'edit');

		$this->get($folder);
		$this->get($file);

		ob_end_clean();
	}

}