<?php	// $Format:SambaDAV: commit %h @ %cd$

# Copyright (C) 2013, 2014  Bokxing IT, http://www.bokxing-it.nl
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

namespace SambaDAV;

class Log
{
	const NONE  = 0;
	const ERROR = 1;
	const WARN  = 2;
	const INFO  = 3;
	const DEBUG = 4;
	const TRACE = 5;

	private $lnames =
		[ self::NONE  => 'none'
		, self::ERROR => 'error'
		, self::WARN  => 'warn'
		, self::INFO  => 'info'
		, self::DEBUG => 'debug'
		, self::TRACE => 'trace'
		] ;

	private $level;
	private $filename = null;

	public function
	__construct ($level = self::WARN, $filename = null)
	{
		$this->level = $level;
		$this->filename = $filename;

		if (is_null($this->filename)) {
			$this->filename = strftime(dirname(dirname(dirname(__FILE__))).'/log/%Y-%m-%d.log');
		}
	}

	public function
	error ()
	{
		$this->log(self::ERROR, func_get_args());
	}

	public function
	warn ()
	{
		$this->log(self::WARN, func_get_args());
	}

	public function
	info ()
	{
		$this->log(self::INFO, func_get_args());
	}

	public function
	debug ()
	{
		$this->log(self::DEBUG, func_get_args());
	}

	public function
	trace ()
	{
		$this->log(self::TRACE, func_get_args());
	}

	private function
	log ($level, $args)
	{
		// Message logged below threshold?
		if ($level > $this->level) {
			return;
		}
		$message = strftime('%Y-%m-%d %H:%M:%S: ') . $this->lnames[$level] . ': ';

		// Treat first argument as a sprintf format string, the rest as arguments:
		$message .= call_user_func_array('sprintf', $args);

		if ($fp = $this->fileOpenLockAppend()) {
			fwrite($fp, $message);
			$this->fileCloseUnlock($fp);
		}
	}

	private function
	fileOpenLockAppend ()
	{
		// Open the file for appending, lock it.
		// Returns file handle, or false on error.
		if (($fd = fopen($this->filename, 'a')) === false) {
			return false;
		}
		if ((flock($fd, LOCK_EX)) === false) {
			fclose($fd);
			return false;
		}
		chmod($this->filename, 0600);
		return $fd;
	}

	private function
	fileCloseUnlock ($fd)
	{
		fflush($fd);
		flock($fd, LOCK_UN);
		fclose($fd);
	}
}
