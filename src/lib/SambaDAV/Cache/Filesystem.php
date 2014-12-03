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

namespace SambaDAV\Cache;

class Filesystem extends \SambaDAV\Cache
{
	private $basedir = '/dev/shm/webfolders';

	// Create all files -rw------
	private $umask = 0177;

	// This file is used as a lockfile and timestamp for the cache cleanup process,
	// so that no cache writes can be done during a cleanup, and vice versa:
	private $semaphore = null;

	// The maximal age in seconds that a cache file can reach before being
	// eligible for reaping (and also the minimal age the semaphore file 
	// must have before starting cleanup):
	const MAX_AGE = 60;

	public function
	__construct ($basedir = null, $umask = null)
	{
		if ($basedir !== null) {
			$this->basedir = $basedir;
		}
		if ($umask !== null) {
			$this->umask = $umask;
		}
		// Make directory if not exists:
		if (is_dir($this->basedir) === false) {
			mkdir($this->basedir, 0700, false);
		}
		$this->semaphore = "{$this->basedir}/last_cleaned";
	}

	public function
	write ($key, $data, $timeout)
	{
		umask($this->umask);

		// There is a slight race condition here with the cache cleanup
		// processes: if we fopen() a stale file, the cleanup process could
		// unlink it before we manage to modify its mtime by calling
		// ftruncate(). The worst that can happen is a potential cache miss on
		// the next lookup. That's not worth serializing cache access for.
		if (($fd = fopen($this->filename($key), 'a')) === false) {
			return false;
		}
		if (flock($fd, LOCK_EX) === false) {
			fclose($fd);
			return false;
		}
		$result = $this->writefd($fd, $data);

		flock($fd, LOCK_UN);
		fclose($fd);

		return true;
	}

	public function
	read ($key, &$data, $timeout)
	{
		if (($fd = @fopen($this->filename($key), 'r')) === false) {
			return false;
		}
		if (flock($fd, LOCK_SH) === false) {
			fclose($fd);
			return false;
		}
		// Try to read into $data:
		$result = $this->readfd($fd, $data, $timeout);

		flock($fd, LOCK_UN);
		fclose($fd);

		return $result;
	}

	public function
	delete ($key)
	{
		// Try to get blocking lock on cache clean semaphore file:
		// If this call fails, just steam ahead regardless:
		$fd = $this->semaphoreOpen(true);
		@unlink($this->filename($key));
		if ($fd) $this->semaphoreClose($fd);
	}

	public function
	clean ()
	{
		// Must acquire the cache clean semaphore, else assume
		// that another cleanup thread is already running:
		if (($fd = $this->semaphoreGet()) === false) {
			return false;
		}
		// Remove files older than self::MAX_AGE seconds:
		if (($dir = opendir($this->basedir)) === false) {
			$this->semaphoreClose($fd);
			return false;
		}
		while (($entry = readdir($dir)) !== false) {
			if ($entry === '.' || $entry === '..' || $entry === 'last_cleaned') {
				continue;
			}
			$file = "{$this->basedir}/$entry";
			if ((time() - filemtime($file)) > self::MAX_AGE) {
				unlink($file);
			}
		}
		closedir($dir);

		// ftruncate() updates the mtime on the semaphore file:
		ftruncate($fd, 0);
		$this->semaphoreClose($fd);
		return true;
	}

	private function
	filename ($key)
	{
		return "{$this->basedir}/$key";
	}

	private function
	writefd ($fd, $raw)
	{
		// Discard data, place file pointer at offset 0:
		if (ftruncate($fd, 0) === false) {
			return false;
		}
		// Write to file:
		if (fwrite($fd, $raw) === false) {
			return false;
		}
		fflush($fd);
		return true;
	}

	private function
	readfd ($fd, &$data, $timeout)
	{
		// Get file stats:
		if (($stat = fstat($fd)) === false) {
			return false;
		}
		// If file is too old, data is stale:
		if (time() - $stat['mtime'] > $timeout) {
			return false;
		}
		// If file is empty, data is invalid:
		if ($stat['size'] === 0) {
			return false;
		}
		// Read file contents:
		if (($data = fread($fd, $stat['size'])) === false) {
			return false;
		}
		return true;
	}

	private function
	semaphoreGet ()
	{
		// Returns false if we are not eligible to clean (semaphore
		// file is locked, or timestamp too recent), else returns fd
		// of semaphore file.
		if (($fd = $this->semaphoreOpen(false)) === false) {
			return false;
		}
		// Get last modification time:
		if (($stat = fstat($fd)) === false) {
			$this->semaphoreClose($fd);
			return false;
		}
		// File modification time too recent?
		if ((time() - $stat['mtime']) < self::MAX_AGE) {
			$this->semaphoreClose($fd);
			return false;
		}
		return $fd;
	}

	private function
	semaphoreOpen ($block)
	{
		// Returns the fd of the semaphore file in locked state if we
		// were able to acquire it, else false.

		// Try to open the semaphore file in append mode.
		// This can fail if there is no cache directory, in which case
		// we don't need to do cleanup anyway.
		if (($fd = @fopen($this->semaphore, 'a')) === false) {
			return false;
		}
		// In blocking mode, try to obtain exclusive lock:
		if ($block && flock($fd, LOCK_EX)) {
			return $fd;
		}
		// In non-blocking mode, try to obtain exclusive lock:
		if (flock($fd, LOCK_EX | LOCK_NB, $wouldblock) && !$wouldblock) {
			return $fd;
		}
		// Could not lock file, return unsuccessfully:
		fclose($fd);
		return false;
	}

	private function
	semaphoreClose ($fd)
	{
		flock($fd, LOCK_UN);
		fclose($fd);
	}
}
