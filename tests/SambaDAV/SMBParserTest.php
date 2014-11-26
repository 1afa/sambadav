<?php

namespace SambaDAV;

class SMBParserTest extends \PHPUnit_Framework_TestCase
{
	public function
	testConnectFailed ()
	{
		$outp = "Connection to server failed (Error NT_STATUS_UNSUCCESSFUL)\n";
		$fd = fopen('data://text/plain,' . $outp, 'r');

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('getStdoutHandle'),
			array(null, null));

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

		$proc = $this->getMock('\SambaDAV\SMBClient\Process',
			array('getStdoutHandle'),
			array(null, null));

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
}
