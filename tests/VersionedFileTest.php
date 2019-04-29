<?php
/**
 * @package    silverstripe-versionedfiles
 * @subpackage tests
 */
class VersionedFileTest extends FunctionalTest
{
    protected $usesDatabase = true;

    /**
     * @var Folder
     */
    protected $folder;

    /**
     * @var File
     */
    protected $file;

    public function setUp()
    {
        parent::setUp();

        $this->folder = Folder::find_or_make(ASSETS_DIR . '/versionedfiles-test');

        $file = $this->folder->getFullPath() . 'test-file.txt';
        file_put_contents($file, 'first-version');

        $this->file = new File();
        $this->file->ParentID = $this->folder->ID;
        $this->file->Filename = $this->folder->getFilename() . 'test-file.txt';
        $this->file->write();

        SecurityToken::disable();
    }

    public function tearDown()
    {
        SecurityToken::enable();

        $this->folder->deleteDatabaseOnly();
        Filesystem::removeFolder($this->folder->getFullPath());

        parent::tearDown();
    }

    public function testMovingFileToAnotherDirectory()
    {
        $oldDir = '/OldDir/';
        $newDir = '/NewDir/';

        // Create a directory
        $oldFolder = Folder::find_or_make($oldDir);

        // Create a file
        file_put_contents($oldFolder->getFullPath() . 'test-file.txt', 'first-content');
        $oldFile = File::create();
        $oldFile->ParentID = $oldFolder->ID;
        $oldFile->Filename = $oldFolder->getFilename() . 'test-file.txt';
        $oldFile->write();


        // Create a new directory & move the file to the new directory
        $newFolder = Folder::find_or_make($newDir);
        $newFile = File::get()->byID($oldFile->ID);
        $newFile->ParentID = $newFolder->ID;
        $newFile->Filename = $newFolder->getFilename() . 'test-file.txt';
        $newFile->write();

        // Assert file exists in new directory
        $this->assertTrue(is_file($newFile->getFullPath()));

        // Delete files
        $newFile->delete();
        $oldFile->delete();

        // Delete directories & files created in this test case
        array_map('unlink', glob(BASE_PATH . '/'. ASSETS_DIR . $oldDir . '/*.*'));
        if (is_dir($oldDir)) {
            rmdir($oldDir);
        }
        array_map('unlink', glob(BASE_PATH . '/'. ASSETS_DIR . $newDir . '/*.*'));
        if (is_dir($newDir)) {
            rmdir($newDir);
        }
    }

    public function testInitialSaveCreatesVersion()
    {
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

    public function testNewVersionIncrementsVersionNumber()
    {
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

    public function testRollbackReplacesFile()
    {
        file_put_contents($this->file->getFullPath(), 'second-version');
        $this->file->createVersion();

        $this->logInWithPermission('ADMIN');
        $this->getFileEditForm();

        $this->assertEquals(
            'second-version',
            file_get_contents($this->file->CurrentVersion()->getFullPath())
        );

        $form = $this->mainSession->lastPage()->getFormById('Form_ItemEditForm');
        $url  = Director::makeRelative($form->getAction()->asString());
        $data = array();

        foreach ($form->_widgets as $widget) {
            $data[$widget->getName()] = $widget->getValue();
        }

        $this->post($url, array_merge($data, array(
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

    /**
     * We need to test that the _versions folder isn't completed wiped by
     * {@link VersionedFileExtension::onBeforeDelete()} when there is more than the file currently being deleted.
     */
    public function testOnBeforeDelete()
    {
        // Create the second file
        $file2 = $this->folder->getFullPath() . 'test-file2.txt';
        file_put_contents($file2, 'first-version');

        $file2Obj = new File();
        $file2Obj->ParentID = $this->folder->ID;
        $file2Obj->Filename = $this->folder->getFilename() . 'test-file2.txt';
        $file2Obj->write();

        // Create a second version of the second file
        file_put_contents($file2Obj->getFullPath(), 'second-version');
        $file2Obj->createVersion();

        // Delete the second file
        $file2Obj->delete();

        // Ensure the _versions folder still exists
        $this->assertTrue(is_dir($this->folder->getFullPath()));
        $this->assertTrue(is_dir($this->folder->getFullPath() . '/_versions'));

        // Now delete the first file, and ensure the _versions folder no longer exists
        $this->file->delete();

        $this->assertTrue(is_dir($this->folder->getFullPath()));
        $this->assertFalse(is_dir($this->folder->getFullPath() . '/_versions'));

        // Now create another file to ensure that the _versions folder can be successfully re-created
        $file3 = $this->folder->getFullPath() . 'test-file3.txt';
        file_put_contents($file3, 'first-version');

        $file3Obj = new File();
        $file3Obj->ParentID = $this->folder->ID;
        $file3Obj->Filename = $this->folder->getFilename() . 'test-file3.txt';
        $file3Obj->write();

        $this->assertTrue(is_file($file3Obj->getFullPath()));
        $this->assertTrue(is_dir($this->folder->getFullPath() . '/_versions'));
    }

    protected function getFileEditForm()
    {
        $admin  = new AssetAdmin();
        $folder = Controller::join_links(
            $admin->Link(), 'show', $this->folder->ID
        );
        $file = Controller::join_links(
            $admin->Link(), 'EditForm/field/File/item', $this->file->ID, 'edit'
        );

        $this->get($folder);
        $this->get($file);
    }
}
