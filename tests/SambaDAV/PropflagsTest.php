<?php

namespace SambaDAV;

class PropflagsTest extends \PHPUnit_Framework_TestCase
{
	public function
	testFromWin32Neg_A ()
	{
		$flags = new Propflags();
		$this->assertFalse($flags->from_win32('hallo'));
	}

	public function
	testFromWin32Neg_B ()
	{
		$flags = new Propflags();
		$this->assertFalse($flags->from_win32(null));
	}

	public function
	testFromWin32Pos_A ()
	{
		$flags = new PropFlags();
		$this->assertTrue($flags->from_win32('00000080'));	// Normal
	}

	public function
	testFromWin32Pos_B ()
	{
		$flags = new PropFlags();
		$flags->from_win32('00000080');
		$this->assertEquals('00000080', $flags->to_win32());
	}

	public function
	testFromWin32Pos_C ()
	{
		$flags = new PropFlags();
		$flags->from_win32('00000001');
		$this->assertEquals('00000001', $flags->to_win32());
	}

	public function
	testFromWin32Pos_D ()
	{
		$flags = new PropFlags();

		// No normal flag set:
		$flags->from_win32('00000000');

		// After roundtrip, normal flag set:
		$this->assertEquals('00000080', $flags->to_win32());
	}

	public function
	testFromWin32Pos_E ()
	{
		$flags = new PropFlags();

		// Set normal flag:
		$flags->from_win32('00000080');

		// Set other flag:
		$flags->set('R', true);

		// After roundtrip, normal flag unset:
		$this->assertEquals('00000001', $flags->to_win32());
	}

	public function
	testFromWin32Pos_F ()
	{
		$flags = new PropFlags();

		// Set R flag flag:
		$flags->from_win32('00000001');

		// Unset again:
		$flags->set('R', false);

		// After roundtrip, normal flag set:
		$this->assertEquals('00000080', $flags->to_win32());
	}

	public function
	testFromSmbflags_A ()
	{
		$flags = new PropFlags('H');
		$this->assertEquals('00000002', $flags->to_win32());
	}

	public function
	testFromSmbflags_B ()
	{
		$flags = new PropFlags('N');
		$this->assertEquals('00000080', $flags->to_win32());	// Normal
	}

	public function
	testFromSmbflags_C ()
	{
		$flags = new PropFlags('RH');
		$this->assertEquals('00000003', $flags->to_win32());
	}

	public function
	testDiff_A ()
	{
		$a = new PropFlags('H');
		$b = new PropFlags('R');
		$this->assertEquals(array('-r', '+h'), $b->diff($a));
	}

	public function
	testDiff_B ()
	{
		$a = new PropFlags('H');
		$b = new PropFlags('H');
		$this->assertEquals(array(), $b->diff($a));
	}

	public function
	testDiff_C ()
	{
		$a = new PropFlags('N');
		$b = new PropFlags('H');
		$this->assertEquals(array('+h'), $a->diff($b));
	}

	public function
	testDiff_D ()
	{
		$a = new PropFlags('N');
		$b = new PropFlags('H');
		$this->assertEquals(array('-h'), $b->diff($a));
	}
}
