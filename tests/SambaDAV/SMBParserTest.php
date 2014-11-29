<?php

namespace SambaDAV;

class SMBParserTest extends \PHPUnit_Framework_TestCase
{
	public function
	testConnectFailed ()
	{
		$outp = "Connection to server failed (Error NT_STATUS_UNSUCCESSFUL)\n";
		$fd = fopen('data://text/plain,' . $outp, 'r');
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('getStdoutHandle'),
			array(null, null, $log));

		$proc->method('getStdoutHandle')
		     ->willReturn($fd);

		$parser = new SMBClient\Parser($proc);
		$this->assertEquals(SMB::STATUS_UNAUTHENTICATED, $parser->getShares());
	}

	public function
	testGetShares ()
	{
		// Actual output from `smbclient -gNL //server`:
		$outp = <<<EOT
Anonymous login successful
Printer|pdf|PDF printer
Disk|print$|Printer Drivers
IPC|IPC$|IPC Service (fixeer)
Disk|webshare|
Disk|recordings|Recorded data
Disk|osx_server|test
Disk|iso|Recovery CD and DVDs
Disk|intranet|
Disk|ftp|
Printer|dell1720|Dell Laser Printer 1720dn
Printer|Xerox6128|Xerox6128
Disk|acltest|
Anonymous login successful
Server|JUPITER|jupiter
Server|W81VM|
Workgroup|TESTING|TESTINGPC09
Workgroup|DEMO|DEMO-SERVER
Workgroup|S4|SAMBA4
Workgroup|WORKGROUP|RICHARD
EOT;
		$fd = fopen('data://text/plain,' . $outp, 'r');
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('getStdoutHandle'),
			array(null, null, $log));

		$proc->method('getStdoutHandle')
		     ->willReturn($fd);

		$parser = new SMBClient\Parser($proc);
		$this->assertEquals(array
			( 'webshare'
			, 'recordings'
			, 'osx_server'
			, 'iso'
			, 'intranet'
			, 'ftp'
			, 'acltest'
			) , $parser->getShares());
	}

	public function
	testGetDiskUsage ()
	{
		// Actual output from `smbclient [options] -c du`:
		$outp = <<<EOT

		50000 blocks of size 2097152. 48993 blocks available
Total number of bytes: 108982041
EOT;
		$fd = fopen('data://text/plain,' . $outp, 'r');
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('getStdoutHandle'),
			array(null, null, $log));

		$proc->method('getStdoutHandle')
		     ->willReturn($fd);

		$parser = new SMBClient\Parser($proc);
		$this->assertEquals(array
			( 2097152 * (50000 - 48993)	// Used space (bytes)
			, 2097152 * 48993		// Free space (bytes)
			) , $parser->getDiskUsage());
	}

	public function
	testGetDiskUsageFail ()
	{
		// Actual output from `smbclient [options] -c du` with wrong pass:
		$outp = "session setup failed: NT_STATUS_LOGON_FAILURE\n";

		$fd = fopen('data://text/plain,' . $outp, 'r');
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('getStdoutHandle'),
			array(null, null, $log));

		$proc->method('getStdoutHandle')
		     ->willReturn($fd);

		$parser = new SMBClient\Parser($proc);
		$this->assertEquals(SMB::STATUS_UNAUTHENTICATED, $parser->getDiskUsage());
	}
}
