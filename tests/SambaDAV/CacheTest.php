<?php

namespace SambaDAV;

class CacheTest extends \PHPUnit_Framework_TestCase
{
	private $testdata =
		"Lorem ipsum dolor sit amet, consectetur adipisicing elit,
		sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\n";

	private function
	ivSize ()
	{
		return mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
	}

	public function
	testEncode ()
	{
		$cache = new Cache\Null();
		$enc = $cache->encrypt($this->testdata, $this->ivSize(), 'abcd');
		$this->assertTrue(is_string($enc));
		$this->assertTrue($enc !== $this->testdata);
	}

	public function
	testEncodeRoundtrip ()
	{
		$cache = new Cache\Null();
		$enc = $cache->encrypt($this->testdata, $this->ivSize(), 'abcd');
		$dec = $cache->decrypt($enc, $this->ivSize(), 'abcd');
		$this->assertTrue(is_string($dec));

		// The decoded string can have trailing null bytes from the way
		// the decoding works; only check the relevant substring:
		$this->assertStringStartsWith($this->testdata, $dec);
	}

	public function
	testSerialize ()
	{
		$cache = new Cache\Null();
		$this->assertTrue($cache->serialize($this->testdata, $raw, $this->ivSize(), 'abcd'));
		$this->assertTrue(is_string($raw));
	}

	public function
	testSerializeRoundtrip ()
	{
		$cache = new Cache\Null();
		$this->assertTrue($cache->serialize($this->testdata, $raw, $this->ivSize(), 'abcd'));
		$this->assertTrue($cache->unserialize($data, $raw, $this->ivSize(), 'abcd'));
		$this->assertEquals($data, $this->testdata);
	}

	public function
	testFilesystemWrite ()
	{
		$tempdir = sys_get_temp_dir();
		$cache = new Cache\Filesystem($tempdir);
		$this->assertTrue($cache->write('foo', $this->testdata, 100));
	}

	public function
	testFilesystemRoundtrip ()
	{
		$tempdir = sys_get_temp_dir();
		$cache = new Cache\Filesystem($tempdir);
		$this->assertTrue($cache->write('foo', $this->testdata, 100));
		$this->assertTrue($cache->read('foo', $data, 100));
		$this->assertEquals($data, $this->testdata);
	}

	public function
	testFilesystemDelete ()
	{
		$tempdir = sys_get_temp_dir();
		$cache = new Cache\Filesystem($tempdir);
		$this->assertTrue($cache->write('foo', $this->testdata, 100));
		$this->assertTrue(is_null($cache->delete('foo')));
	}
}
