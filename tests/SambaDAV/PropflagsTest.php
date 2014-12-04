<?php

namespace SambaDAV;

class PropflagsTest extends \PHPUnit_Framework_TestCase
{
	public function
	testFromWin32Neg_A ()
	{
		$flags = new Propflags();
		$this->assertFalse($flags->fromWin32('hallo'));
	}

	public function
	testFromWin32Neg_B ()
	{
		$flags = new Propflags();
		$this->assertFalse($flags->fromWin32(null));
	}

	public function
	testFromWin32Pos_A ()
	{
		$flags = new PropFlags();
		$this->assertTrue($flags->fromWin32('00000080'));	// Normal
	}

	public function
	testFromWin32Pos_B ()
	{
		$flags = new PropFlags();
		$flags->fromWin32('00000080');
		$this->assertEquals('00000080', $flags->toWin32());
	}

	public function
	testFromWin32Pos_C ()
	{
		$flags = new PropFlags();
		$flags->fromWin32('00000001');
		$this->assertEquals('00000001', $flags->toWin32());
	}

	public function
	testFromWin32Pos_D ()
	{
		$flags = new PropFlags();

		// No normal flag set:
		$flags->fromWin32('00000000');

		// After roundtrip, normal flag set:
		$this->assertEquals('00000080', $flags->toWin32());
	}

	public function
	testFromWin32Pos_E ()
	{
		$flags = new PropFlags();

		// Set normal flag:
		$flags->fromWin32('00000080');

		// Set other flag:
		$flags->set('R', true);

		// After roundtrip, normal flag unset:
		$this->assertEquals('00000001', $flags->toWin32());
	}

	public function
	testFromWin32Pos_F ()
	{
		$flags = new PropFlags();

		// Set R flag flag:
		$flags->fromWin32('00000001');

		// Unset again:
		$flags->set('R', false);

		// After roundtrip, normal flag set:
		$this->assertEquals('00000080', $flags->toWin32());
	}

	public function
	testFromSmbflags_A ()
	{
		$flags = new PropFlags('H');
		$this->assertEquals('00000002', $flags->toWin32());
	}

	public function
	testFromSmbflags_B ()
	{
		$flags = new PropFlags('N');
		$this->assertEquals('00000080', $flags->toWin32());	// Normal
	}

	public function
	testFromSmbflags_C ()
	{
		$flags = new PropFlags('RH');
		$this->assertEquals('00000003', $flags->toWin32());
	}

	public function
	testSetFalse ()
	{
		$a = new PropFlags();
		$a->set('H', false);
		$this->assertFalse($a->get('H'));
		$this->assertTrue($a->get('N'));	// Normal
	}

	public function
	testSetTrue ()
	{
		$a = new PropFlags();
		$a->set('H', true);
		$this->assertTrue($a->get('H'));
		$this->assertFalse($a->get('N'));	// Normal
	}

	public function
	testSetStringZero ()
	{
		$a = new PropFlags();
		$a->set('A', (bool)'0');
		$this->assertFalse($a->get('A'));
		$this->assertTrue($a->get('N'));	// Normal
	}

	public function
	testSetStringOne ()
	{
		$a = new PropFlags();
		$a->set('A', (bool)'1');
		$this->assertTrue($a->get('A'));
		$this->assertFalse($a->get('N'));	// Normal
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
