<?php

namespace SambaDAV;

class URITest extends \PHPUnit_Framework_TestCase
{
	public function
	uriFullProvider ()
	{
		return
		[ [ [ 'server', 'share', '/path/to/file' ]
		  , '//server/share/path/to/file'
		  ]
		, [ [ 'server', 'share' ]
		  , '//server/share'
		  ]
		, [ [ 'server', 'share', 'path' ]
		  , '//server/share/path'
		  ]
		, [ [ 'server', 'share', '//path////to///file////' ]
		  , '//server/share/path/to/file'
		  ]
		, [ [ null, 'server', null, 'share', null, '//path////to///file////' ]
		  , '//server/share/path/to/file'
		  ]
		, [ [ ]
		  , '//'
		  ]
		, [ [ '' ]
		  , '//'
		  ]
		] ;
	}

	/**
	 * @dataProvider uriFullProvider
	 */
	public function
	testUriFull ($parts, $expect)
	{
		$uri = new URI($parts);
		$this->assertEquals($expect, $uri->uriFull());

		// Add parts one by one; should give same result:
		$uri = new URI();
		foreach ($parts as $part) {
			$uri->addParts($part);
		}
		$this->assertEquals($expect, $uri->uriFull());
	}

	public function
	uriServerShareProvider ()
	{
		return
		[ [ [ 'server', 'share', '/path/to/file' ]
		  , '//server/share'
		  ]
		, [ [ 'server', 'share', null ]
		  , '//server/share'
		  ]
		, [ [ 'server', null ]
		  , '//server'
		  ]
		] ;
	}

	/**
	 * @dataProvider uriServerShareProvider
	 */
	public function
	testUriServerShare ($parts, $expect)
	{
		$uri = new URI($parts);
		$this->assertEquals($expect, $uri->uriServerShare());

		// Add parts one by one; should give same result:
		$uri = new URI();
		foreach ($parts as $part) {
			$uri->addParts($part);
		}
		$this->assertEquals($expect, $uri->uriServerShare());
	}

	public function
	addPartsProvider ()
	{
		return
		[ [ [ 'server', 'share', '/path/to' ]
		  , [ 'file' ]
		  , '//server/share/path/to/file'
		  ]
		, [ [ 'server', 'share' ]
		  , [ 'path' ]
		  , '//server/share/path'
		  ]
		, [ [ 'server', 'share', 'path///' ]
		  , [ 'to', 'file' ]
		  , '//server/share/path/to/file'
		  ]
		, [ [ '\\\\server\\share\\path' ]
		  , [ '\\to\\\\', 'file\\' ]
		  , '//server/share/path/to/file'
		  ]
		] ;
	}

	/**
	 * @dataProvider addPartsProvider
	 */
	public function
	testAddParts ($parts, $adds, $expect)
	{
		$uri = new URI($parts);
		$uri->addParts($adds);
		$this->assertEquals($expect, $uri->uriFull());
	}

	public function
	testRename ()
	{
		$uri = new URI('server', 'share', '/path/to/file');
		$uri->rename('foo');
		$this->assertEquals('//server/share/path/to/foo', $uri->uriFull());
	}

	public function
	nameProvider ()
	{
		return
		[ [ [ 'server', 'share', '/path/to/file' ]
		  , 'file'
		  ]
		, [ [ 'server', 'share', 'path/' ]
		  , 'path'
		  ]
		, [ [ 'server', 'share' ]
		  , 'share'
		  ]
		, [ [ 'server' ]
		  , 'server'
		  ]
		] ;
	}

	/**
	 * @dataProvider nameProvider
	 */
	public function
	testName ($parts, $expect)
	{
		$uri = new URI($parts);
		$this->assertEquals($expect, $uri->name());
	}

	public function
	testNameRename ()
	{
		$uri = new URI('server', 'share', '/path/to/file/name');
		$uri->rename('foo');
		$this->assertEquals('foo', $uri->name());
	}

	public function
	parentDirProvider ()
	{
		return
		[ [ [ 'server', 'share', '/path/to/file/name' ]
		  , '/path/to/file'
		  ]
		, [ [ 'server', 'share', '/path' ]
		  , '/'
		  ]
		, [ [ 'server', 'share', '/path/to' ]
		  , '/path'
		  ]
		] ;
	}

	/**
	 * @dataProvider parentDirProvider
	 */
	public function
	testParentDir ($parts, $expect)
	{
		$uri = new URI($parts);
		$this->assertEquals($expect, $uri->parentDir());
	}
}
