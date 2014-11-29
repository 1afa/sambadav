<?php	// $Format:SambaDAV: commit %h @ %cd$

# Copyright (C) 2013  Bokxing IT, http://www.bokxing-it.nl
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# Project page: <https://github.com/bokxing-it/sambadav/>

namespace SambaDAV\SMBClient;

class Process
{
	public $fd = false;
	private $proc = false;
	private $auth = false;
	private $config;
	private $log;

	public function
	__construct ($auth, $config, $log)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->log = $log;
	}

	public function
	open ($args, $smbcmd)
	{
		// $args is assumed to have been shell-escaped by caller;
		// append any extra smbclient options if specified:
		if (isset($this->config->smbclient_extra_opts) && is_string($this->config->smbclient_extra_opts)) {
			$args .= ' '.$this->config->smbclient_extra_opts;
		}
		$pipes = array
			( 0 => array('pipe', 'r')	// child reads from stdin
			, 1 => array('pipe', 'w')	// child writes to stdout
			, 2 => array('pipe', 'w')	// child writes to stderr
			, 3 => array('pipe', 'r')	// child reads from fd#3
			, 4 => array('pipe', 'r')	// child reads from fd#4
			, 5 => array('pipe', 'w')	// child writes to fd#5
			) ;

		$env = array
			( 'HOME' => '/dev/null'		// Nice restrictive environment
			, 'SHELL' => '/bin/false'
			// smbclient outputs filenames in utf8, also needs support in the environment
			// (with an ASCII locale you only get the lower bytes):
			, 'LC_ALL' => 'en_US.UTF-8'
			) ;

		$cmd = ($this->auth->anonymous)
			? sprintf('%s --debuglevel=0 --no-pass %s', $this->config->smbclient_path, $args)
			: sprintf('%s --debuglevel=0 --authentication-file=/proc/self/fd/3 %s', $this->config->smbclient_path, $args);

		if (!($this->proc = proc_open($cmd, $pipes, $this->fd, '/', $env))) {
			return false;
		}
		if (!is_resource($this->proc)) {
			return false;
		}
		if (!$this->writeAuthFile()) {
			return false;
		}
		if (!$this->writeCommand($smbcmd)) {
			return false;
		}
		return true;
	}

	public function
	getStdoutHandle ()
	{
		// Return the file handle corresponding to stdout:
		return $this->fd[1];
	}

	public function
	getOutputStreamHandle ()
	{
		// Return the file handle corresponding to the program's
		// write pipe:
		return $this->fd[5];
	}

	public function
	getStdinHandle ()
	{
		// Separate getter for test mocking purposes:
		return $this->fd[0];
	}

	public function
	closeStdinHandle ()
	{
		fclose($this->getStdinHandle());
	}

	public function
	getAuthFileHandle ()
	{
		// Separate getter for test mocking purposes:
		return $this->fd[3];
	}

	public function
	closeAuthFileHandle ()
	{
		fclose($this->getAuthFileHandle());
	}

	public function
	writeAuthFile ()
	{
		if ($this->auth->anonymous === false)
		{
			$creds = array();

			if (is_string($user = $this->auth->sambaUsername())) {
				$creds[] = "username=$user";
			}
			if (is_string($pass = $this->auth->pass)) {
				$creds[] = "password=$pass";
			}
			if (is_string($domain = $this->auth->sambaDomain())) {
				$creds[] = "domain=$domain";
			}
			if (fwrite($this->getAuthFileHandle(), implode("\n", $creds)) === false) {
				$this->closeAuthFileHandle();
				return false;
			}
		}
		$this->closeAuthFileHandle();
		return true;
	}

	public function
	writeCommand ($smbcmd)
	{
		if ($smbcmd !== false) {
			if (fwrite($this->getStdinHandle(), $smbcmd) === false) {
				$this->closeStdinHandle();
				return false;
			}
		}
		$this->closeStdinHandle();
		return true;
	}

	public function
	__destruct ()
	{
		if (is_array($this->fd)) {
			foreach ($this->fd as $fd) {
				if (is_resource($fd)) {
					fclose($fd);
				}
			}
		}
		if (is_resource($this->proc)) {
			proc_close($this->proc);
		}
	}
}
