<?php

namespace SambaDAV;

class AuthTest extends \PHPUnit_Framework_TestCase
{
	public function
	testFillPattern_A ()
	{
		$auth = new Auth(null);
		$auth->user = 'foo\\bar@baz';
		$this->assertEquals('foo', $auth->fillPattern('%w'));
	}

	public function
	testFillPattern_B ()
	{
		$auth = new Auth(null);
		$auth->user = 'foo\\bar@baz';
		$this->assertEquals('bar', $auth->fillPattern('%u'));
	}

	public function
	testFillPattern_C ()
	{
		$auth = new Auth(null);
		$auth->user = 'foo\\bar@baz';
		$this->assertEquals('baz', $auth->fillPattern('%d'));
	}

	public function
	testFillPattern_D ()
	{
		$auth = new Auth(null);
		$auth->user = 'foo\\bar';
		$this->assertEquals('bar', $auth->fillPattern('%u'));
		$this->assertEquals('', $auth->fillPattern('%d'));
	}

	public function
	testFillPattern_E ()
	{
		$auth = new Auth(null);
		$auth->user = 'bar@';
		$this->assertEquals('', $auth->fillPattern('%w'));
		$this->assertEquals('bar', $auth->fillPattern('%u'));
		$this->assertEquals('', $auth->fillPattern('%d'));
	}

	public function
	testFillPattern_F ()
	{
		$auth = new Auth(null);
		$auth->user = 'foo\\bar';
		$this->assertEquals(false, $auth->fillPattern('%d %u %w'));
	}

	public function
	testFillPattern_G ()
	{
		$auth = new Auth(null);
		$auth->user = '';
		$this->assertEquals(false, $auth->fillPattern('%d %u %w'));
	}

	public function
	testFillPattern_H ()
	{
		$auth = new Auth(null);
		$auth->user = 'foo\\@bar';
		$this->assertEquals('', $auth->fillPattern('%u'));
	}

	public function
	testFillPattern_I ()
	{
		$auth = new Auth(null);
		$auth->user = '\\f@';
		$this->assertEquals('f', $auth->fillPattern('%u'));
	}

	public function
	testFillPattern_J ()
	{
		$auth = new Auth(null);
		$auth->user = 'f@';
		$this->assertEquals('f', $auth->fillPattern('%u'));
	}

	public function
	testFillPattern_K ()
	{
		$auth = new Auth(null);
		$auth->user = '\\f';
		$this->assertEquals('f', $auth->fillPattern('%u'));
	}
}
