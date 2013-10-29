<?php
/**
 * @package    silverstripe-versionedfiles
 * @subpackage tests
 */
class VersionedImageTest extends FunctionalTest {

	protected $usesDatabase = true;

	protected $image;
	
	protected $reference;

	public function setUp() {
		parent::setUp();

		$path = ASSETS_PATH . '/test-image.png';
		$referencePath = ASSETS_PATH . '/test-image-reference.png';

		// Create two base images, A and B.
		$first = imagecreatetruecolor(200, 100);
		imagefill($first, 0, 0, imagecolorallocate($first, 0, 0, 0));
		imagepng($first, $path);
		imagepng($first, $referencePath);

		$this->image = new Image();
		$this->image->Filename = 'assets/test-image.png';
		$this->image->write();

		$this->reference = new Image();
		$this->reference->Filename = 'assets/test-image-reference.png';
		$this->reference->write();

		// Add version #2 to image A.
		$second = imagecreatetruecolor(100, 200);
		imagefill($second, 0, 0, imagecolorallocate($first, 255, 255, 255));
		imagepng($second, $path);

		$this->image->createVersion();
	}

	public function tearDown() {
		$this->image->delete();
		if($this->reference) $this->reference->delete();

		parent::tearDown();
	}

	/**
	 * Reverting the file should also regenerate all the transformed files.
	 * We check this here by applying the transform to both the tested file and the reference file,
	 * then reverting and checking if the transform still applies.
	 *
	 * @dataProvider resampleProvider
	 */
	public function testVersionChangeResamplesImage($method, array $arguments) {
		call_user_func_array(array($this->image, $method), $arguments);
		call_user_func_array(array($this->reference, $method), $arguments);

		$this->image->setVersionNumber(1);
		$this->image->write();

		$dir = dirname($this->reference->getFullPath());
		$reference = basename($this->reference->getFullPath());
		$tested = basename($this->image->getFullPath());
		$args = implode('', $arguments);

		$this->assertFileEquals(
			"$dir/_resampled/$method$args-$reference",
			"$dir/_resampled/$method$args-$tested",
			'Reverting an image version re-applies all transforms already in place.'
		);
	}

	public function resampleProvider() {
		return array(
			array('SetSize',      array(50, 50)),
			array('PaddedImage',  array(500, 200, '999999')),
			array('SetWidth',     array(80)),
			array('SetHeight',    array(80)),
			array('SetRatioSize', array(150, 300))
		);
	}

}
