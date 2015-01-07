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
# Project page: <https://github.com/1afa/sambadav/>

namespace SambaDAV;

abstract class Log
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

	public $level;

	// Commit $data to log. Returns true/false.
	abstract protected function commit ($level, $message);

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

	public function
	setLevel ($string)
	{
		// Try to find string in lnames array:
		if (($level = array_search($string, $this->lnames)) === false) {
			return false;
		}
		$this->level = $level;
		return true;
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

		return $this->commit($level, $message);
	}
}
