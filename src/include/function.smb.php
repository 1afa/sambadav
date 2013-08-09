<?php	// $Format:SambaDAV: commit %h @ %cd$
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
require_once 'class.smbprocess.php';
require_once 'class.smbparser.php';

function smb_get_shares ($server, $user, $pass)
{
	$args = sprintf('--grepable --list %s', escapeshellarg("//$server"));
	$proc = new \SambaDAV\SMBClient\Process($user, $pass);

	if ($proc->open($args, false) === false) {
		return STATUS_SMBCLIENT_ERROR;
	}
	$parser = new \SambaDAV\SMBClient\Parser($proc);
	return $parser->getShares();
}

function smb_ls ($user, $pass, $server, $share, $path)
{
	log_trace("smb_ls \"//$server/$share$path\"\n");

	if (smb_check_pathname($path) === false) {
		return STATUS_INVALID_NAME;
	}
	$args = escapeshellarg("//$server/$share");
	$scmd = smb_mk_cmd($path, 'ls');
	$proc = new \SambaDAV\SMBClient\Process($user, $pass);

	if ($proc->open($args, $scmd) === false) {
		return STATUS_SMBCLIENT_ERROR;
	}
	$parser = new \SambaDAV\SMBClient\Parser($proc);
	return $parser->getListing();
}

function smb_du ($user, $pass, $server, $share)
{
	log_trace("smb_du \"//$server/$share\"\n");

	$args = escapeshellarg("//$server/$share");
	$scmd = smb_mk_cmd('/', 'du');
	$proc = new \SambaDAV\SMBClient\Process($user, $pass);

	if ($proc->open($args, $scmd) === false) {
		return STATUS_SMBCLIENT_ERROR;
	}
	$parser = new \SambaDAV\SMBClient\Parser($proc);
	return $parser->getDiskUsage();
}

function smb_get ($server, $share, $path, $file, $proc)
{
	log_trace("smb_get \"//$server/$share$path/$file\"\n");

	if (smb_check_pathname($path) === false
	 || smb_check_filename($file) === false) {
		return STATUS_INVALID_NAME;
	}
	$args = escapeshellarg("//$server/$share");
	$scmd = smb_mk_cmd($path, "get \"$file\" /proc/self/fd/5");

	// NB: because we want to return an open file handle, the caller needs
	// to supply the Process class. Otherwise the proc and the fds are
	// local to this function and are garbage collected upon return:
	if ($proc->open($args, $scmd) === false) {
		return STATUS_SMBCLIENT_ERROR;
	}
	fclose($proc->fd[1]);
	fclose($proc->fd[2]);
	fclose($proc->fd[4]);
	return STATUS_OK;
}

function smb_put ($user, $pass, $server, $share, $path, $file, $data, &$md5)
{
	log_trace("smb_put \"//$server/$share$path/$file\"\n");

	if (smb_check_pathname($path) === false
	 || smb_check_filename($file) === false) {
		return STATUS_INVALID_NAME;
	}
	$args = escapeshellarg("//$server/$share");
	$scmd = smb_mk_cmd($path, "put /proc/self/fd/4 \"$file\"");
	$proc = new \SambaDAV\SMBClient\Process($user, $pass);

	if ($proc->open($args, $scmd) === false) {
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
		stream_copy_to_stream($data, $proc->fd[4]);
		stream_filter_remove($md5_filter);
		$md5 = md5s_get_hash();
	}
	else {
		if (fwrite($proc->fd[4], $data) === false) {
			return STATUS_SMBCLIENT_ERROR;
		}
		$md5 = md5($data);
	}
	fclose($proc->fd[4]);
	$parser = new \SambaDAV\SMBClient\Parser($proc);
	return $parser->getStatus();
}

function smb_cmd_simple ($user, $pass, $server, $share, $path, $cmd)
{
	// A helper function that sends a simple (silent)
	// command to smbclient and reports the result status.

	log_trace("smb_cmd_simple: \"//$server/$share$path\": $cmd\n");

	if (smb_check_pathname($path) === false) {
		return STATUS_INVALID_NAME;
	}
	$args = escapeshellarg("//$server/$share");
	$scmd = smb_mk_cmd($path, $cmd);
	$proc = new \SambaDAV\SMBClient\Process($user, $pass);

	if ($proc->open($args, $scmd) === false) {
		return STATUS_SMBCLIENT_ERROR;
	}
	$parser = new \SambaDAV\SMBClient\Parser($proc);
	return $parser->getStatus();
}

function smb_mk_cmd ($path, $cmd)
{
	// First cd to the path, then run the command.
	// There's a reason we create a two-part command with a 'cd' to reach
	// the destination directory instead of using the more obvious
	// '--directory' switch. This method allows arbitrarily long directory
	// names, does not leak the directory information to the process table,
	// and avoids the pitfalls associated with shell escaping. The code
	// paths taken internally by smbclient are virtually identical anyway.

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
	return (strpbrk($filename, $bad) === false);
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
	return (strpbrk($pathname, $bad) === false);
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

function smb_setmode ($user, $pass, $server, $share, $path, $filename, $modeflags)
{
	return smb_cmd_simple($user, $pass, $server, $share, $path,
		"setmode \"$filename\" \"$modeflags\"");
}

function smb_allinfo ($user, $pass, $server, $share, $path, $dirname)
{
	return smb_cmd_simple($user, $pass, $server, $share, $path,
		"allinfo \"$dirname\"");
}
