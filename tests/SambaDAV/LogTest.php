<?php

namespace SambaDAV;

class LogTest extends \PHPUnit_Framework_TestCase
{
	public function
	testError ()
	{
		$log = $this->getMock('\SambaDAV\Log\Filesystem', array('commit'));
		$log->expects($this->once())
		    ->method('commit')
		    ->with($this->equalTo(Log::ERROR), $this->stringContains(': error: NOOO'));

		$log->error("NOOO");
	}

	public function
	testThresholdDenied ()
	{
		// Log message is below threshold; expect no call:
		$log = $this->getMock('\SambaDAV\Log\Filesystem', array('commit'));
		$log->expects($this->exactly(0))
		    ->method('commit');

		$log->debug("NOOO");
	}

	public function
	testThresholdAllowed ()
	{
		// Log message is above threshold; expect call:
		$log = $this->getMock('\SambaDAV\Log\Filesystem', array('commit'), array(Log::DEBUG));
		$log->expects($this->once())
		    ->method('commit')
		    ->with($this->equalTo(Log::DEBUG), $this->stringContains(': debug: Hello world'));

		$log->debug('Hello world');
	}
}
