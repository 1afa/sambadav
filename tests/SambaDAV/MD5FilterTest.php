<?php

namespace SambaDAV;

class MD5FilterTest extends \PHPUnit_Framework_TestCase
{
	public function
	test_A ()
	{
		// Create a stream from a string:
		$string = "";
		$stream = fopen('data://text/plain,' . $string, 'r');

		$filterOutput = new MD5FilterOutput();
		stream_filter_register('md5sum', '\SambaDAV\MD5Filter');
		$filter = stream_filter_append($stream, 'md5sum', STREAM_FILTER_READ, $filterOutput);
		stream_get_contents($stream);
		stream_filter_remove($filter);
		fclose($stream);

		$this->assertEquals(md5($string), $filterOutput->hash);
	}

	public function
	test_B ()
	{
		// Create a stream from a string:
		$string = "Hello, world";
		$stream = fopen('data://text/plain,' . $string, 'r');

		$filterOutput = new MD5FilterOutput();
		stream_filter_register('md5sum', '\SambaDAV\MD5Filter');
		$filter = stream_filter_append($stream, 'md5sum', STREAM_FILTER_READ, $filterOutput);
		stream_get_contents($stream);
		stream_filter_remove($filter);
		fclose($stream);

		$this->assertEquals(md5($string), $filterOutput->hash);
	}
}
