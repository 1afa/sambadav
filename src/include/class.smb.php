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

require_once dirname(dirname(__FILE__)).'/config/config.inc.php';
require_once 'class.log.php';
require_once 'streamfilter.md5.php';
require_once 'class.smbprocess.php';
require_once 'class.smbparser.php';

class SMB
{
	const STATUS_OK			= 0;
	const STATUS_NOTFOUND		= 1;
	const STATUS_UNAUTHENTICATED	= 2;
	const STATUS_INVALID_NAME	= 3;
	const STATUS_SMBCLIENT_ERROR	= 4;

	public static function
	getShares ($server, $user, $pass)
	{
		$args = sprintf('--grepable --list %s', escapeshellarg("//$server"));
		$proc = new \SambaDAV\SMBClient\Process($user, $pass);

		if ($proc->open($args, false) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
		}
		$parser = new \SambaDAV\SMBClient\Parser($proc);
		return $parser->getShares();
	}

	public static function
	ls ($user, $pass, $server, $share, $path)
	{
		Log::trace("SMB::ls \"//$server/$share$path\"\n");

		if (self::checkPathname($path) === false) {
			return self::STATUS_INVALID_NAME;
		}
		$args = escapeshellarg("//$server/$share");
		$scmd = self::makeCmd($path, 'ls');
		$proc = new \SambaDAV\SMBClient\Process($user, $pass);

		if ($proc->open($args, $scmd) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
		}
		$parser = new \SambaDAV\SMBClient\Parser($proc);
		return $parser->getListing();
	}

	public static function
	du ($user, $pass, $server, $share)
	{
		Log::trace("SMB::du \"//$server/$share\"\n");

		$args = escapeshellarg("//$server/$share");
		$scmd = self::makeCmd('/', 'du');
		$proc = new \SambaDAV\SMBClient\Process($user, $pass);

		if ($proc->open($args, $scmd) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
		}
		$parser = new \SambaDAV\SMBClient\Parser($proc);
		return $parser->getDiskUsage();
	}

	public static function
	get ($server, $share, $path, $file, $proc)
	{
		Log::trace("SMB::get \"//$server/$share$path/$file\"\n");

		if (self::checkPathname($path) === false) {
			return self::STATUS_INVALID_NAME;
		}
		if (self::checkFilename($file) === false) {
			return self::STATUS_INVALID_NAME;
		}
		$args = escapeshellarg("//$server/$share");
		$scmd = self::makeCmd($path, "get \"$file\" /proc/self/fd/5");

		// NB: because we want to return an open file handle, the caller needs
		// to supply the Process class. Otherwise the proc and the fds are
		// local to this function and are garbage collected upon return:
		if ($proc->open($args, $scmd) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
		}
		fclose($proc->fd[1]);
		fclose($proc->fd[2]);
		fclose($proc->fd[4]);
		return self::STATUS_OK;
	}

	public static function
	put ($user, $pass, $server, $share, $path, $file, $data, &$md5)
	{
		Log::trace("SMB::put \"//$server/$share$path/$file\"\n");

		if (self::checkPathname($path) === false) {
			return self::STATUS_INVALID_NAME;
		}
		if (self::checkFilename($file) === false) {
			return self::STATUS_INVALID_NAME;
		}
		$args = escapeshellarg("//$server/$share");
		$scmd = self::makeCmd($path, "put /proc/self/fd/4 \"$file\"");
		$proc = new \SambaDAV\SMBClient\Process($user, $pass);

		if ($proc->open($args, $scmd) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
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
			$filterOutput = new MD5FilterOutput();
			stream_filter_register('md5sum', '\SambaDAV\MD5Filter');
			$filter = stream_filter_append($data, 'md5sum', STREAM_FILTER_READ, $filterOutput);
			stream_copy_to_stream($data, $proc->fd[4]);
			stream_filter_remove($filter);
			$md5 = $filterOutput->hash;
		}
		else {
			if (fwrite($proc->fd[4], $data) === false) {
				return self::STATUS_SMBCLIENT_ERROR;
			}
			$md5 = md5($data);
		}
		fclose($proc->fd[4]);
		$parser = new \SambaDAV\SMBClient\Parser($proc);
		return $parser->getStatus();
	}

	private static function
	cmdSimple ($user, $pass, $server, $share, $path, $cmd)
	{
		// A helper function that sends a simple (silent)
		// command to smbclient and reports the result status.

		Log::trace("SMB::cmdSimple: \"//$server/$share$path\": $cmd\n");

		if (self::checkPathname($path) === false) {
			return self::STATUS_INVALID_NAME;
		}
		$args = escapeshellarg("//$server/$share");
		$scmd = self::makeCmd($path, $cmd);
		$proc = new \SambaDAV\SMBClient\Process($user, $pass);

		if ($proc->open($args, $scmd) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
		}
		$parser = new \SambaDAV\SMBClient\Parser($proc);
		return $parser->getStatus();
	}

	private static function
	makeCmd ($path, $cmd)
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

	private static function
	checkFilename ($filename)
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

	private static function
	checkPathname ($pathname)
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

	public static function
	rm ($user, $pass, $server, $share, $path, $filename)
	{
		return self::cmdSimple($user, $pass, $server, $share, $path,
			"rm \"$filename\"");
	}

	public static function
	rename ($user, $pass, $server, $share, $path, $oldname, $newname)
	{
		return self::cmdSimple($user, $pass, $server, $share, $path,
			"rename \"$oldname\" \"$newname\"");
	}

	public static function
	mkdir ($user, $pass, $server, $share, $path, $dirname)
	{
		return self::cmdSimple($user, $pass, $server, $share, $path,
			"mkdir \"$dirname\"");
	}

	public static function
	rmdir ($user, $pass, $server, $share, $path, $dirname)
	{
		return self::cmdSimple($user, $pass, $server, $share, $path,
			"rmdir \"$dirname\"");
	}

	public static function
	setMode ($user, $pass, $server, $share, $path, $filename, $modeflags)
	{
		return self::cmdSimple($user, $pass, $server, $share, $path,
			"setmode \"$filename\" \"$modeflags\"");
	}

	public static function
	allInfo ($user, $pass, $server, $share, $path, $dirname)
	{
		return self::cmdSimple($user, $pass, $server, $share, $path,
			"allinfo \"$dirname\"");
	}
}
