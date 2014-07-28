<?php	// $Format:SambaDAV: commit %h @ %cd$
/*
 * Copyright (C) 2013, 2014  Bokxing IT, http://www.bokxing-it.nl
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Project page: <https://github.com/bokxing-it/sambadav/>
 *
 */

namespace SambaDAV;

class Log
{
	// Set this to true to enable trace-level logging:
	private static $traceEnabled = false;
	private static $filename = null;

	public static function
	trace ()
	{
		if (self::$traceEnabled === false) {
			return;
		}
		if (func_num_args() === 0) {
			return;
		}
		// Treat first argument as a sprintf format string, the rest as arguments:
		$args = func_get_args();
		$message = call_user_func_array('sprintf', $args);

		if ($fp = self::fileOpenLockAppend()) {
			fwrite($fp, $message);
			self::fileCloseUnlock($fp);
		}
	}

	private static function
	initFilename ()
	{
		self::$filename = strftime(dirname(dirname(dirname(__FILE__))).'/log/trace-%Y-%m-%d.log');
	}

	private static function
	fileOpenLockAppend ()
	{
		// Open the file for appending, lock it.
		// Returns file handle, or false on error.
		if (self::$filename === null) {
			self::initFilename();
		}
		if (($fd = fopen(self::$filename, 'a')) === false) {
			return false;
		}
		if ((flock($fd, LOCK_EX)) === false) {
			fclose($fd);
			return false;
		}
		chmod(self::$filename, 0600);
		return $fd;
	}

	private static function
	fileCloseUnlock ($fd)
	{
		fflush($fd);
		flock($fd, LOCK_UN);
		fclose($fd);
	}
}
