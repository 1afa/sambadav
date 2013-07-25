<?php	// $Id: function.smb.php,v 1.15 2013/07/23 16:04:33 alfred Exp $
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

require_once dirname(dirname(__FILE__)).'/config/config.inc.php';
require_once 'common.inc.php';
require_once 'function.log.php';
require_once 'streamfilter.md5.php';

function smb_proc_open ($user, $pass, $args, $smbcmd, &$proc, &$fds)
{
	// Returns TRUE on success, FALSE on error.
	// Leaves open a bunch of file descriptors that
	// can be used for reading/writing to the process.

	$pipes = array(
		0 => array('pipe', 'r'),	// child reads from stdin
		1 => array('pipe', 'w'),	// child writes to stdout
		2 => array('pipe', 'w'),	// child writes to stderr
		3 => array('pipe', 'r'),	// child reads from fd#3
		4 => array('pipe', 'r'),	// child reads from fd#4
		5 => array('pipe', 'w')		// child writes to fd#5
	);
	$env = array(
		'HOME' => '/dev/null',		// Nice restrictive environment
		'SHELL' => '/bin/false',
		// smbclient outputs filenames in utf8, also needs support in the environment
		// (with an ASCII locale you only get the lower bytes):
		'LC_ALL' => 'en_US.UTF-8'
	);
	// Do anonymous login if ANONYMOUS_ONLY is set, or if ANONYMOUS_ALLOW
	// is set and not all credentials are filled:
	$anonymous = ANONYMOUS_ONLY || (ANONYMOUS_ALLOW && (FALSE($user) || FALSE($pass)));

	// $args is assumed to have been shell-escaped by caller:
	$cmd = ($anonymous)
		? sprintf('%s -N %s', SMBCLIENT_PATH, $args)
		: sprintf('%s -A /proc/self/fd/3 %s', SMBCLIENT_PATH, $args);

	if (FALSE($proc = proc_open($cmd, $pipes, $fds, '/', $env)) || !is_resource($proc)) {
		return FALSE;
	}
	// Write SMB command, if any, to stdin (fd0):
	if (!FALSE($smbcmd)) {
		if (FALSE(fwrite($fds[0], $smbcmd))) {
			smb_proc_close($proc, $fds);
			return FALSE;
		}
	}
	// Write username and password to fd3:
	if (FALSE($anonymous)) {
		$creds = (FALSE($pass))
			? "username=$user"
			: "username=$user\npassword=$pass";

		if (FALSE(fwrite($fds[3], $creds))) {
			smb_proc_close($proc, $fds);
			return FALSE;
		}
	}
	// close read pipes, but leave stdout, stderr, fd4 and fd5 open:
	fclose($fds[0]);
	fclose($fds[3]);
	return TRUE;
}

function smb_proc_close ($proc, $fds)
{
	for ($i = 0; $i < 6; $i++) {
		if (isset($fds[$i]) && is_resource($fds[$i])) {
			fclose($fds[$i]);
		}
	}
	if (is_resource($proc)) {
		proc_close($proc);
	}
}

function smb_get_line ($fd, &$nline)
{
	// Returns FALSE if no more lines;
	// Returns Array(errorcode) if error found;
	// Returns the line as a string if all is well.

	if (!is_resource($fd)) {
		return FALSE;
	}
	while (TRUE)
	{
		if (FALSE($line = fgets($fd))) {
			return FALSE;
		}
		if ($nline++ < 2 && preg_match('/(NT_STATUS_[A-Z0-9_]*)/', $line, $matches) === 1) {
			switch ($matches[1])
			{
				// This is the only status we consider acceptable:
				case 'NT_STATUS_OK':
					// Beware strange 'continue' semantics in PHP, in a switch
					// it's equivalent to 'break'; need two levels to continue loop:
					continue 2;

				case 'NT_STATUS_LOGON_FAILURE':
				case 'NT_STATUS_ACCESS_DENIED':	// TODO: this can also mean "not writable"
					return Array(STATUS_UNAUTHENTICATED);

				case 'NT_STATUS_NO_SUCH_FILE':
				case 'NT_STATUS_BAD_NETWORK_NAME':
				case 'NT_STATUS_OBJECT_PATH_NOT_FOUND':
				case 'NT_STATUS_OBJECT_NAME_NOT_FOUND':
					return Array(STATUS_NOTFOUND);

				// All other statuses, assume unauthenticated:
				default:
					return Array(STATUS_UNAUTHENTICATED);
			}
		}
		return $line;
	}
}

function smb_parse_file_line ($line)
{
	// Parses a line of smbclient's ls output and returns an array with the following fields:
	//  0 - filename;
	//  1 - flags;
	//  2 - size;
	//  3 - Unix timestamp.

	// The printf format that smbclient uses to print the file data can be
	// found in ./source3/client/client.c, line 549 or thereabouts.
	//
	// d_printf("  %-30s%7.7s %8.0f  %s",
	// 		finfo->name,
	// 		attrib_string(finfo->mode),
	//		(double)finfo->size,
	//		time_to_asc(t));
	//
	// So the string looks like this:
	// - two spaces;
	// - at least 30 characters of space-padded filename;
	// - exactly 7 characters of flags/spaces;
	// - a space;
	// - at least 8 characters of file length;
	// - two spaces;
	// - time string.
	// The time string is its own can of worms, but fairly deterministic-looking.
	// Match from right to left:

	//                  Name Flags          Size                   Mon            Mar            8         13:00:07  2010
	if (preg_match("/^  (.*)([A-Za-z ]{7}) ([0-9]{8,}|[0-9 ]{8})  (.*)$/", rtrim($line), $matches) === 0) {
		return FALSE;
	}
	$output = Array(
		rtrim($matches[1]),	// filename
		trim($matches[2]),	// flagstring
		(int)$matches[3]	// filesize (bytes)
	);
	// Create Unix timestamp from freeform date string:
	$date = date_parse($matches[4]);

	$output[] = (FALSE($date)) ? 0 : mktime($date['hour'], $date['minute'], $date['second'], $date['month'], $date['day'], $date['year']);

	return $output;
}

function smb_get_status ($fds)
{
	// Parses the smbclient output on stdout, returns STATUS_OK
	// if everything could be read without encountering errors
	// (as parsed by smb_get_line), else it returns the error code.
	$nline = 0;
	while (!FALSE($line = smb_get_line($fds[1], $nline))) {
		if (is_array($line)) {
			return $line[0];
		}
	}
	return STATUS_OK;
}

function smb_get_resources ($user, $pass, $server)
{
	$args = sprintf('-g -L %s', escapeshellarg("//$server"));

	if (FALSE(smb_proc_open($user, $pass, $args, FALSE, $proc, $fds))) {
		return STATUS_SMBCLIENT_ERROR;
	}
	$nline = 0;
	$resources = array();
	while (!FALSE($line = smb_get_line($fds[1], $nline))) {
		if (is_array($line)) {
			smb_proc_close($proc, $fds);
			return $line[0];
		}
		$resources[] = $line;
	}
	smb_proc_close($proc, $fds);
	return $resources;
}

function smb_get_shares ($server, $user, $pass)
{
	// if $resources is not an array, it's an error code:
	if (!is_array($resources = smb_get_resources($user, $pass, $server))) {
		return $resources;
	}
	$shares = Array();
	foreach ($resources as $line) {
		if (strpos($line, 'Disk|') !== 0) {
			continue;
		}
		if (FALSE($term = strpos($line, '|', 5)) || $term === 5) {
			continue;
		}
		$name = substr($line, 5, $term - 5);
		// "Special" shares have a name ending with '$', discard those:
		if (substr($name, -1, 1) === '$') {
			continue;
		}
		$shares[] = $name;
	}
	return $shares;
}

function smb_ls ($user, $pass, $server, $share, $path)
{
	log_trace("smb_ls \"//$server/$share$path\"\n");

	if (FALSE(smb_check_pathname($path))) {
		return STATUS_INVALID_NAME;
	}
	$args = escapeshellarg("//$server/$share");
	$scmd = smb_mk_cmd($path, 'ls');

	if (FALSE(smb_proc_open($user, $pass, $args, $scmd, $proc, $fds))) {
		return STATUS_SMBCLIENT_ERROR;
	}
	$nline = 0;
	$ret = Array();
	while (!FALSE($line = smb_get_line($fds[1], $nline))) {
		if (is_array($line)) {
			smb_proc_close($proc, $fds);
			return $line[0];
		}
		if (!FALSE($parsed = smb_parse_file_line($line))) {
			$ret[] = $parsed;
		}
	}
	smb_proc_close($proc, $fds);
	return $ret;
}

function smb_du ($user, $pass, $server, $share)
{
	log_trace("smb_du \"//$server/$share\"\n");

	$args = sprintf('%s -D /', escapeshellarg("//$server/$share"));
	$scmd = smb_mk_cmd('/', 'du');

	if (FALSE(smb_proc_open($user, $pass, $args, $scmd, $proc, $fds))) {
		return STATUS_SMBCLIENT_ERROR;
	}
	// The 'du' command only gives a global total for the entire share;
	// the Unix 'du' can do a tally for a subdir, but this one can't.
	$nline = 0;
	while (!FALSE($line = smb_get_line($fds[1], $nline))) {
		if (is_array($line)) {
			smb_proc_close($proc, $fds);
			return $line[0];
		}
		if (preg_match('/([0-9]+) blocks of size ([0-9]+)\. ([0-9]+) blocks available/', $line, $matches) === 0) {
			continue;
		}
		smb_proc_close($proc, $fds);
		return Array(
			$matches[2] * ($matches[1] - $matches[3]),	// used space (bytes)
			$matches[2] * $matches[3]			// available space (bytes)
		);
	}
	smb_proc_close($proc, $fds);
	return FALSE;
}

function smb_get ($user, $pass, $server, $share, $path, $file, &$proc, &$fds)
{
	log_trace("smb_get \"//$server/$share$path/$file\"\n");

	if (FALSE(smb_check_pathname($path))
	 || FALSE(smb_check_filename($file))) {
		return STATUS_INVALID_NAME;
	}
	$args = escapeshellarg("//$server/$share");
	$scmd = smb_mk_cmd($path, "get \"$file\" /proc/self/fd/5");

	// NB: because we want to return an open file handle, the caller needs
	// to supply the proc and fds variables. Otherwise the proc and the fds
	// are local to this function and are garbage collected upon return:
	if (FALSE(smb_proc_open($user, $pass, $args, $scmd, $proc, $fds))) {
		return STATUS_SMBCLIENT_ERROR;
	}
	fclose($fds[1]);
	fclose($fds[2]);
	fclose($fds[4]);
	return STATUS_OK;
}

function smb_put ($user, $pass, $server, $share, $path, $file, $data, &$md5)
{
	log_trace("smb_put \"//$server/$share$path/$file\"\n");

	if (FALSE(smb_check_pathname($path))
	 || FALSE(smb_check_filename($file))) {
		return STATUS_INVALID_NAME;
	}
	$args = escapeshellarg("//$server/$share");
	$scmd = smb_mk_cmd($path, "put /proc/self/fd/4 \"$file\"");

	if (FALSE(smb_proc_open($user, $pass, $args, $scmd, $proc, $fds))) {
		return STATUS_SMBCLIENT_ERROR;
	}
	// If an error occurs, the error message will be on stdout before we
	// even have a chance to upload; but otherwise there won't be anything
	// until we've finished uploading. We can't really use stream_select()
	// here to check if there's already output on stdout, because the
	// process probably hasn't had enough time to talk to the server. We
	// could use a loop to check stream_select(), but at that point you're
	// putting a lot of machinery in place for an exceptional event.

	// $data can be a string or a resource; must deal with both:
	if (is_resource($data)) {
		// Append md5summing streamfilter to input stream:
		stream_filter_register('md5sum', 'md5sum_filter');
		$md5_filter = stream_filter_append($data, 'md5sum');
		stream_copy_to_stream($data, $fds[4]);
		stream_filter_remove($md5_filter);
		$md5 = md5s_get_hash();
	}
	else {
		if (FALSE(fwrite($fds[4], $data))) {
			smb_proc_close($proc, $fds);
			return STATUS_SMBCLIENT_ERROR;
		}
		$md5 = md5($data);
	}
	fclose($fds[4]);
	$status = smb_get_status($fds);
	smb_proc_close($proc, $fds);
	return $status;
}

function smb_cmd_simple ($user, $pass, $server, $share, $path, $cmd)
{
	// A helper function that sends a simple (silent)
	// command to smbclient and reports the result status.

	log_trace("smb_cmd_simple: \"//$server/$share$path\": $cmd\n");

	if (FALSE(smb_check_pathname($path))) {
		return STATUS_INVALID_NAME;
	}
	$args = escapeshellarg("//$server/$share");
	$scmd = smb_mk_cmd($path, $cmd);

	if (FALSE(smb_proc_open($user, $pass, $args, $scmd, $proc, $fds))) {
		return STATUS_SMBCLIENT_ERROR;
	}
	$status = smb_get_status($fds);
	smb_proc_close($proc, $fds);
	return $status;
}

function smb_mk_cmd ($path, $cmd)
{
	// First cd to the path, then run the command:
	return "cd \"$path\"\n$cmd";
}

function smb_check_filename ($filename)
{
	// Windows filenames cannot contain " * : < > ? \ / |
	// or characters 1..31. Also exclude \0 as a matter of course:
	$bad = sprintf(
		'"*:<>?\/|%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c',
		 0,  1,  2,  3,  4,  5,  6,  7,  8,  9,
		10, 11, 12, 13, 14, 15, 16, 17, 18, 19,
		20, 21, 22, 23, 24, 25, 26, 27, 28, 29,
		30, 31
	);
	return FALSE(strpbrk($filename, $bad));
}

function smb_check_pathname ($pathname)
{
	// Exclude the same set of characters as above, with the exception of
	// slashes. We need a sanitizer, because smbclient can be tricked into
	// running local shell commands by feeding it a command starting with
	// '!'. Ensure pathnames do not contain newlines and other special chars
	// (ironically, '!' itself is allowed):
	$bad = sprintf(
		'"*:<>?|%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c',
		 0,  1,  2,  3,  4,  5,  6,  7,  8,  9,
		10, 11, 12, 13, 14, 15, 16, 17, 18, 19,
		20, 21, 22, 23, 24, 25, 26, 27, 28, 29,
		30, 31
	);
	return FALSE(strpbrk($pathname, $bad));
}

function smb_rm ($user, $pass, $server, $share, $path, $filename)
{
	return smb_cmd_simple($user, $pass, $server, $share, $path,
		"rm \"$filename\"");
}

function smb_rename ($user, $pass, $server, $share, $path, $oldname, $newname)
{
	return smb_cmd_simple($user, $pass, $server, $share, $path,
		"rename \"$oldname\" \"$newname\"");
}

function smb_mkdir ($user, $pass, $server, $share, $path, $dirname)
{
	return smb_cmd_simple($user, $pass, $server, $share, $path,
		"mkdir \"$dirname\"");
}

function smb_rmdir ($user, $pass, $server, $share, $path, $dirname)
{
	return smb_cmd_simple($user, $pass, $server, $share, $path,
		"rmdir \"$dirname\"");
}

function smb_allinfo ($user, $pass, $server, $share, $path, $dirname)
{
	return smb_cmd_simple($user, $pass, $server, $share, $path,
		"allinfo \"$dirname\"");
}
