<?php

namespace SambaDAV;

class URITest extends \PHPUnit_Framework_TestCase
{
	public function
	testUriFull_A ()
	{
		$uri = new URI('server', 'share', '/path/to/file');
		$this->assertEquals('//server/share/path/to/file', $uri->uriFull());
	}

	public function
	testUriFull_B ()
	{
		$uri = new URI('server', 'share');
		$this->assertEquals('//server/share', $uri->uriFull());
	}

	public function
	testUriFull_C ()
	{
		$uri = new URI('server', 'share', 'path');
		$this->assertEquals('//server/share/path', $uri->uriFull());
	}

	public function
	testUriFull_D ()
	{
		$uri = new URI('server', 'share', '//path////to///file////');
		$this->assertEquals('//server/share/path/to/file', $uri->uriFull());
	}

	public function
	testUriServerShare_A ()
	{
		$uri = new URI('server', 'share', '/path/to/file');
		$this->assertEquals('//server/share', $uri->uriServerShare());
	}

	public function
	testUriServerShare_B ()
	{
		$uri = new URI('server', 'share', null);
		$this->assertEquals('//server/share', $uri->uriServerShare());
	}

	public function
	testUriServerShare_C ()
	{
		$uri = new URI('server', null);
		$this->assertEquals('//server', $uri->uriServerShare());
	}

	public function
	testAddParts_A ()
	{
		$uri = new URI('server', 'share', '/path/to');
		$uri->addParts('file');
		$this->assertEquals('//server/share/path/to/file', $uri->uriFull());
	}

	public function
	testAddParts_B ()
	{
		$uri = new URI('server', 'share');
		$uri->addParts('path');
		$this->assertEquals('//server/share/path', $uri->uriFull());
	}

	public function
	testAddParts_C ()
	{
		$uri = new URI('server', 'share', 'path///');
		$uri->addParts('to');
		$uri->addParts('file');
		$this->assertEquals('//server/share/path/to/file', $uri->uriFull());
	}

	public function
	testRename_A ()
	{
		$uri = new URI('server', 'share', '/path/to/file');
		$uri->rename('foo');
		$this->assertEquals('//server/share/path/to/foo', $uri->uriFull());
	}

	public function
	testName_A ()
	{
		$uri = new URI('server', 'share', '/path/to/file');
		$this->assertEquals('file', $uri->name());
	}

	public function
	testName_B ()
	{
		$uri = new URI('server', 'share', 'path/');
		$this->assertEquals('path', $uri->name());
	}

	public function
	testName_C ()
	{
		$uri = new URI('server', 'share');
		$this->assertEquals('share', $uri->name());
	}

	public function
	testName_D ()
	{
		$uri = new URI('server');
		$this->assertEquals('server', $uri->name());
	}

	public function
	testName_E ()
	{
		$uri = new URI('server', 'share', '/path/to/file/name');
		$uri->rename('foo');
		$this->assertEquals('foo', $uri->name());
	}

	public function
	testParentDir_A ()
	{
		$uri = new URI('server', 'share', '/path/to/file/name');
		$this->assertEquals('/path/to/file', $uri->parentDir());
	}

	public function
	testParentDir_B ()
	{
		$uri = new URI('server', 'share', '/path');
		$this->assertEquals('/', $uri->parentDir());
	}

	public function
	testParentDir_C ()
	{
		$uri = new URI('server', 'share', '/path/to');
		$this->assertEquals('/path', $uri->parentDir());
	}
}
