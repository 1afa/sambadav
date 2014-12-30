<?php

namespace SambaDAV;

class AuthTest extends \PHPUnit_Framework_TestCase
{
	public function
	fillPatternProvider ()
	{
		return
		[ [ 'foo\\bar@baz', '%w', 'foo' ]
		, [ 'foo\\bar@baz', '%u', 'bar' ]
		, [ 'foo\\bar@baz', '%d', 'baz' ]
		, [ 'foo\\bar',     '%u', 'bar' ]
		, [ 'foo\\bar',     '%d', '' ]
		, [ 'bar@',         '%w', '' ]
		, [ 'bar@',         '%u', 'bar' ]
		, [ 'bar@',         '%d', '' ]
		, [ 'foo\\bar',     '%d %u %w', false ]
		, [ '',             '%d %u %w', false ]
		, [ 'foo\\@bar',    '%u', '' ]
		, [ '\\f@',         '%u', 'f' ]
		, [ 'f@',           '%u', 'f' ]
		, [ '\\f',          '%u', 'f' ]
		] ;
	}

	/**
	 * @dataProvider fillPatternProvider
	 */
	public function
	testFillPattern ($user, $pattern, $expect)
	{
		$auth = new Auth(null);
		$auth->user = $user;
		$this->assertEquals($expect, $auth->fillPattern($pattern));
	}

	public function
	testCheckSambaPatterns ()
	{
		$config = new Config();
		$config->samba_username_pattern = '%u';
		$config->samba_domain_pattern = null;

		$auth = new Auth($config);
		$auth->user = 'joe';

		$auth->checkSambaPatterns();
		$this->assertEquals('joe', $auth->sambaUsername());
		$this->assertEquals(null, $auth->sambaDomain());
	}
}
