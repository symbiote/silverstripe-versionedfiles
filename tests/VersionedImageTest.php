<?php
/**
 * @package    silverstripe-versionedfiles
 * @subpackage tests
 */
class VersionedImageTest extends FunctionalTest {

	protected $usesDatabase = true;

	protected $image, $reference;

	public function setUp() {
		parent::setUp();

		$path         = ASSETS_PATH . '/test-image.png';
		$expectedPath = ASSETS_PATH . '/test-image-expected.png';

		$first = imagecreatetruecolor(200, 100);
		imagefill($first, 0, 0, imagecolorallocate($first, 0, 0, 0));
		imagepng($first, $path);
		imagepng($first, $expectedPath);

		$this->image = new Image();
		$this->image->Filename = ASSETS_DIR . '/test-image.png';
		$this->image->write();

		$this->reference = new Image();
		$this->reference->Filename = ASSETS_DIR . '/test-image-expected.png';
		$this->reference->write();

		$second = imagecreatetruecolor(100, 200);
		imagefill($second, 0, 0, imagecolorallocate($first, 255, 255, 255));
		imagepng($second, $path);

		$this->image->createVersion();
	}

	public function tearDown() {
		parent::tearDown();
		$this->image->delete();
	}

	/**
	 * @dataProvider resampleProvidor
	 */
	public function testVersionChangeResamplesImage($method, array $arguments) {
		call_user_func_array(array($this->image, $method), $arguments);
		call_user_func_array(array($this->reference, $method), $arguments);

		$this->assertFileNotEquals(
			$this->reference->getFullPath(),
			$this->image->getFullPath(),
			'The second image version is different.'
		);

		$this->image->setVersionNumber(1);
		$this->image->write();

		$this->assertFileEquals(
			$this->reference->getFullPath(),
			$this->image->getFullPath(),
			'The rolled back image is the same as the reference.'
		);
	}

	public function resampleProvidor() {
		return array(
			array('SetSize',      array(50, 50)),
			array('PaddedImage',  array(500, 200)),
			array('SetWidth',     array(80)),
			array('SetHeight',    array(80)),
			array('SetRatioSize', array(150, 300))
		);
	}

}