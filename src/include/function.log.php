<?php	// $Id: function.log.php,v 1.5 2013/07/23 16:04:33 alfred Exp $
/*
 * Copyright (C) 2013  Bokxing IT, http://www.bokxing-it.nl
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

date_default_timezone_set('Europe/Berlin');

$trace_log = FALSE;

function log_trace ()
{
	global $trace_log;

	if (!$trace_log || func_num_args() == 0) {
		return;
	}
	// Treat first argument as a sprintf format string, the rest as arguments:
	$args = func_get_args();
	$message = call_user_func_array('sprintf', $args);

	$file = strftime(dirname(dirname(__FILE__)).'/log/trace-%Y-%m-%d.log');

	if ($fp = file_open_lock_append($file)) {
		fwrite($fp, $message);
		file_close_unlock($fp);
	}
}

function file_open_lock_append ($filename)
{
	// Open the file for appending, lock it.
	// Returns file handle, or FALSE on error.
	if (FALSE($fd = fopen($filename, 'a'))) {
		return FALSE;
	}
	if (FALSE(flock($fd, LOCK_EX))) {
		fclose($fd);
		return FALSE;
	}
	chmod($filename, 0600);
	return $fd;
}

function file_close_unlock ($fd)
{
	fflush($fd);
	flock($fd, LOCK_UN);
	fclose($fd);
}
