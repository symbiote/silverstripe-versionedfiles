<?php
/**
 * @package    silverstripe-versionedfiles
 * @subpackage tests
 */
class VersionedFileTest extends FunctionalTest {

	protected $usesDatabase = true;

	/**
	 * @var Folder
	 */
	protected $folder;

	/**
	 * @var File
	 */
	protected $file;

	public function setUp() {
		parent::setUp();

		$this->folder = new Folder();
		$this->folder->Filename = ASSETS_DIR . '/versionedfiles-test';
		$this->folder->write();

		$file = $this->folder->getFullPath() . 'test-file.txt';
		file_put_contents($file, 'first-version');

		$this->file = new File();
		$this->file->ParentID = $this->folder->ID;
		$this->file->Filename = $this->folder->getFilename() . 'test-file.txt';
		$this->file->write();
	}

	public function tearDown() {
		parent::tearDown();

		$this->folder->deleteDatabaseOnly();
		Filesystem::removeFolder($this->folder->getFullPath());
	}

	public function testInitialSaveCreatesVersion() {
		$this->assertNull(
			$this->folder->CurrentVersionID,
			'Folders do not have versions created.'
		);

		$this->assertEquals(
			1, $this->file->getVersionNumber(),
			'Files have initial versions vreated.'
		);

		$this->assertEquals(
			'first-version',
			file_get_contents($this->file->CurrentVersion()->getFullPath()),
			'Files are copied to a stored version directory.'
		);
	}

	public function testNewVersionIncrementsVersionNumber() {
		file_put_contents($this->file->getFullPath(), 'second-version');
		$this->file->createVersion();
		$this->assertEquals(
			2, DataObject::get_by_id('File', $this->file->ID)->getVersionNumber(),
			'The version number has incremented.'
		);

		file_put_contents($this->file->getFullPath(), 'third-version');
		$this->file->createVersion();
		$this->assertEquals(
			3, DataObject::get_by_id('File', $this->file->ID)->getVersionNumber(),
			'The version number has incremented.'
		);
	}

	public function testRollbackReplacesFile() {
		file_put_contents($this->file->getFullPath(), 'second-version');
		$this->file->createVersion();

		$this->logInWithPermission('ADMIN');
		$this->getFileEditForm();

		$this->assertEquals(
			'second-version',
			file_get_contents($this->file->CurrentVersion()->getFullPath())
		);

		$form = $this->mainSession->lastPage()->getFormById('ComplexTableField_Popup_DetailForm');
		$url  = Director::makeRelative($form->getAction()->asString());
		$data = array();

		foreach($form->_widgets as $widget) {
			$data[$widget->getName()] = $widget->getValue();
		}

		$this->post($url, array_merge($data, array (
			'Replace'         => 'rollback',
			'PreviousVersion' => 1,
			'ReplacementFile' => array(),
		)));

		$this->assertEquals(
			'first-version', file_get_contents($this->file->getFullPath())
		);
		$this->assertEquals(
			1, DataObject::get_by_id('File', $this->file->ID)->getVersionNumber()
		);
	}

	protected function getFileEditForm() {
		Form::disable_all_security_tokens();

		$admin  = new AssetAdmin();
		$folder = Controller::join_links(
			$admin->Link(), 'show', $this->folder->ID
		);
		$file = Controller::join_links(
			$admin->Link(), 'EditForm/field/Files/item', $this->file->ID, 'edit'
		);

		$this->get($folder);
		$this->get($file);
	}

}