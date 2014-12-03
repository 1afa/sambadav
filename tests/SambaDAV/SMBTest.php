<?php

namespace SambaDAV;

class SMBTest extends \PHPUnit_Framework_TestCase
{
	public function
	testGetShares ()
	{
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('open'),
			array(null, null, $log));

		$smb = new SMB(null, null, $log);
		$uri = new URI('//server/share/dir');

		$proc->expects($this->once())
		     ->method('open')
		     ->with($this->equalTo("--grepable --list '//server'"), false);

		$smb->getShares($uri, $proc);
	}

	public function
	testLs_A ()
	{
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('open'),
			array(null, null, $log));

		$smb = new SMB(null, null, $log);
		$uri = new URI('//server/share/dir');

		$proc->expects($this->once())
		     ->method('open')
		     ->with($this->equalTo("'//server/share'"), $this->equalTo("cd \"/dir\"\nls"));

		$smb->ls($uri, $proc);
	}

	public function
	testLs_B ()
	{
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('open'),
			array(null, null, $log));

		$smb = new SMB(null, null, $log);
		$uri = new URI('//server/share/dir/subdir');

		$proc->expects($this->once())
		     ->method('open')
		     ->with($this->equalTo("'//server/share'"), $this->equalTo("cd \"/dir/subdir\"\nls"));

		$smb->ls($uri, $proc);
	}

	public function
	testDu ()
	{
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('open'),
			array(null, null, $log));

		$smb = new SMB(null, null, $log);
		$uri = new URI('//server/share/dir/subdir');

		$proc->expects($this->once())
		     ->method('open')
		     ->with($this->equalTo("'//server/share'"), $this->equalTo("cd \"/dir/subdir\"\ndu"));

		$smb->du($uri, $proc);
	}

	public function
	testGet ()
	{
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('open'),
			array(null, null, $log));

		$smb = new SMB(null, null, $log);
		$uri = new URI('//server/share/dir/file.txt');

		$proc->expects($this->once())
		     ->method('open')
		     ->with($this->equalTo("'//server/share'"), $this->equalTo("cd \"/dir\"\nget \"file.txt\" /proc/self/fd/5"));

		$smb->get($uri, $proc);
	}

	public function
	testPut ()
	{
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('open'),
			array(null, null, $log));

		$smb = new SMB(null, null, $log);
		$uri = new URI('//server/share/dir/file.txt');

		$proc->expects($this->once())
		     ->method('open')
		     ->with($this->equalTo("'//server/share'"), $this->equalTo("cd \"/dir\"\nput /proc/self/fd/4 \"file.txt\""))
		     ->willReturn(false);

		$smb->put($uri, null, $md5, $proc);
	}
}
