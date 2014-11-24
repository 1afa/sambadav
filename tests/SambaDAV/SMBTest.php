<?php

namespace SambaDAV;

class SMBTest extends \PHPUnit_Framework_TestCase
{
	public function
	testGetShares ()
	{
		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('open'),
			array(null, null));

		$smb = new SMB(null, null);
		$uri = new URI('//server/share/dir');

		$proc->expects($this->once())
		     ->method('open')
		     ->with($this->equalTo("--grepable --list '//server'"), false);

		$smb->getShares($uri, $proc);
	}

	public function
	testLs_A ()
	{
		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('open'),
			array(null, null));

		$smb = new SMB(null, null);
		$uri = new URI('//server/share/dir');

		$proc->expects($this->once())
		     ->method('open')
		     ->with($this->equalTo("'//server/share'"), $this->equalTo("cd \"/dir\"\nls"));

		$smb->ls($uri, $proc);
	}

	public function
	testLs_B ()
	{
		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('open'),
			array(null, null));

		$smb = new SMB(null, null);
		$uri = new URI('//server/share/dir/subdir');

		$proc->expects($this->once())
		     ->method('open')
		     ->with($this->equalTo("'//server/share'"), $this->equalTo("cd \"/dir/subdir\"\nls"));

		$smb->ls($uri, $proc);
	}

	public function
	testDu ()
	{
		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('open'),
			array(null, null));

		$smb = new SMB(null, null);
		$uri = new URI('//server/share/dir/subdir');

		$proc->expects($this->once())
		     ->method('open')
		     ->with($this->equalTo("'//server/share'"), $this->equalTo("cd \"/dir/subdir\"\ndu"));

		$smb->du($uri, $proc);
	}
}
