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

class SMB
{
	const STATUS_OK			= 0;
	const STATUS_NOTFOUND		= 1;
	const STATUS_UNAUTHENTICATED	= 2;
	const STATUS_INVALID_NAME	= 3;
	const STATUS_SMBCLIENT_ERROR	= 4;

	private $auth;
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
	getShares ($uri, $proc = null)
	{
		$args = sprintf('--grepable --list %s', escapeshellarg($uri->uriServer()));

		if (is_null($proc)) {
			$proc = new \SambaDAV\SMBClient\Process($this->auth, $this->config);
		}
		if ($proc->open($args, false) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
		}
		$parser = new \SambaDAV\SMBClient\Parser($proc);
		return $parser->getShares();
	}

	public function
	ls ($uri, $proc = null)
	{
		$this->log->trace("SMB::ls '%s'\n", $uri->uriFull());

		if ($uri->isWinSafe() === false) {
			return self::STATUS_INVALID_NAME;
		}
		$args = escapeshellarg($uri->uriServerShare());
		$scmd = $this->makeCmd($uri->path(), 'ls');

		// Allow injection of a proc object for testing:
		if (is_null($proc)) {
			$proc = new \SambaDAV\SMBClient\Process($this->auth, $this->config);
		}
		if ($proc->open($args, $scmd) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
		}
		$parser = new \SambaDAV\SMBClient\Parser($proc);
		return $parser->getListing();
	}

	public function
	du ($uri, $proc = null)
	{
		$this->log->trace("SMB::du '%s'\n", $uri->uriFull());

		$args = escapeshellarg($uri->uriServerShare());
		$scmd = $this->makeCmd($uri->path(), 'du');

		if (is_null($proc)) {
			$proc = new \SambaDAV\SMBClient\Process($this->auth, $this->config);
		}
		if ($proc->open($args, $scmd) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
		}
		$parser = new \SambaDAV\SMBClient\Parser($proc);
		return $parser->getDiskUsage();
	}

	public function
	get ($uri, $proc)
	{
		$this->log->trace("SMB::get '%s'\n", $uri->uriFull());

		if ($uri->isWinSafe() === false) {
			return self::STATUS_INVALID_NAME;
		}
		$args = escapeshellarg($uri->uriServerShare());
		$scmd = $this->makeCmd($uri->parentDir(), sprintf('get "%s" /proc/self/fd/5', $uri->name()));

		// NB: because we want to return an open file handle, the caller needs
		// to supply the Process class. Otherwise the proc and the fds are
		// local to this function and are garbage collected upon return:
		if ($proc->open($args, $scmd) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
		}
		if (is_resource($proc->fd[1])) fclose($proc->fd[1]);
		if (is_resource($proc->fd[2])) fclose($proc->fd[2]);
		if (is_resource($proc->fd[4])) fclose($proc->fd[4]);
		return self::STATUS_OK;
	}

	public function
	put ($uri, $data, &$md5, $proc = null)
	{
		$this->log->trace("SMB::put '%s'\n", $uri->uriFull());

		if ($uri->isWinSafe() === false) {
			return self::STATUS_INVALID_NAME;
		}
		$args = escapeshellarg($uri->uriServerShare());
		$scmd = $this->makeCmd($uri->parentDir(), sprintf('put /proc/self/fd/4 "%s"', $uri->name()));

		if (is_null($proc)) {
			$proc = new \SambaDAV\SMBClient\Process($this->auth, $this->config);
		}
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

	private function
	cmdSimple ($uri, $path, $cmd)
	{
		// A helper function that sends a simple (silent)
		// command to smbclient and reports the result status.

		$this->log->trace("SMB::cmdSimple: '%s' '%s'\n", $cmd, $path);

		if ($uri->isWinSafe() === false) {
			return self::STATUS_INVALID_NAME;
		}
		$args = escapeshellarg($uri->uriServerShare());
		$scmd = $this->makeCmd($path, $cmd);
		$proc = new \SambaDAV\SMBClient\Process($this->auth, $this->config);

		if ($proc->open($args, $scmd) === false) {
			return self::STATUS_SMBCLIENT_ERROR;
		}
		$parser = new \SambaDAV\SMBClient\Parser($proc);
		return $parser->getStatus();
	}

	private function
	makeCmd ($path, $cmd)
	{
		// First cd to the path, then run the command.
		// There's a reason we create a two-part command with a 'cd' to reach
		// the destination directory instead of using the more obvious
		// '--directory' switch. This method allows arbitrarily long directory
		// names, does not leak the directory information to the process table,
		// and avoids the pitfalls associated with shell escaping. The code
		// paths taken internally by smbclient are virtually identical anyway.

		return sprintf("cd \"%s\"\n%s", $path, $cmd);
	}

	public function
	rm ($uri)
	{
		// $uri is the URI of the file to remove:
		return $this->cmdSimple($uri, $uri->parentDir(),
			sprintf('rm "%s"', $uri->name()));
	}

	public function
	rename ($uri, $newname)
	{
		// $uri is the URI of the file or dir to rename:
		$newuri = clone $uri;
		$newuri->rename($newname);
		if ($newuri->isWinSafe() === false) {
			return self::STATUS_INVALID_NAME;
		}
		return $this->cmdSimple($uri, $uri->parentDir(),
			sprintf('rename "%s" "%s"', $uri->name(), $newname));
	}

	public function
	mkdir ($uri, $dirname)
	{
		// $uri is the URI of the dir in which to make the new dir:
		$newuri = clone $uri;
		$newuri->addParts($dirname);
		if ($newuri->isWinSafe() === false) {
			return self::STATUS_INVALID_NAME;
		}
		return $this->cmdSimple($uri, $uri->path(),
			sprintf('mkdir "%s"', $dirname));
	}

	public function
	rmdir ($uri)
	{
		// $uri is the URI of the dir to remove:
		return $this->cmdSimple($uri, $uri->parentDir(),
			sprintf('rmdir "%s"', $uri->name()));
	}

	public function
	setMode ($uri, $modeflags)
	{
		// $uri is the URI of the file to process:
		return $this->cmdSimple($uri, $uri->parentDir(),
			sprintf('setmode "%s" "%s"', $uri->name(), $modeflags));
	}
}
