<?php

namespace SambaDAV;

class FileTest extends \PHPUnit_Framework_TestCase
{
	public function
	testGetName ()
	{
		$file = new File(null, null, null, null, new URI('//server/share/dir/file.txt'), null, null, null, null);
		$this->assertEquals('file.txt', $file->getName());
	}
}
