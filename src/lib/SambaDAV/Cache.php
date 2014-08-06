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

class Cache
{
	public static $config = null;

	// Stub out the constructor so this static class can never be instantiated:
	private function __construct () {}

	public static function
	init ($config)
	{
		// This file is used as a lockfile and timestamp for the cache cleanup process,
		// so that no cache writes can be done during a cleanup, and vice versa:
		$config->cache_clean_semaphore = $config->cache_dir.'/last_cleaned';

		self::$config = $config;
	}

	private static function
	filename ($auth, $function, $uri)
	{
		// Filename is unique for function, URI and username:
		return sprintf('%s/%s', self::$config->cache_dir, sha1($auth->user . $function . $uri->uriFull(), false));
	}

	private static function
	readfd ($fd, $iv_size, $user_key, $timeout, &$data)
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
		if ($stat['size'] == 0) {
			return false;
		}
		// Read file contents:
		if (($raw = fread($fd, $stat['size'])) === false) {
			return false;
		}
		// Decode:
		if (($dec = self::dec($raw, $iv_size, $user_key)) === false) {
			return false;
		}
		// Uncompress:
		if (($unc = gzuncompress($dec)) === false) {
			return false;
		}
		// Unserialize to get plain data:
		if (($data = unserialize($unc)) === false) {
			return false;
		}
		return true;
	}

	private static function
	read ($filename, $iv_size, $user_key, $timeout, &$data)
	{
		if (($fd = @fopen($filename, 'r')) === false) {
			return false;
		}
		if (flock($fd, LOCK_SH) === false) {
			fclose($fd);
			return false;
		}
		// Try to read into $data:
		$result = self::readfd($fd, $iv_size, $user_key, $timeout, $data);

		flock($fd, LOCK_UN);
		fclose($fd);

		return $result;
	}

	private static function
	writefd ($fd, $iv_size, $user_key, $data)
	{
		// Serialize the raw data:
		if (($ser = serialize($data)) === false) {
			return false;
		}
		// Compress:
		if (($com = gzcompress($ser)) === false) {
			return false;
		}
		// Encrypt:
		if (($enc = self::enc($com, $iv_size, $user_key)) === false) {
			return false;
		}
		// Discard data, place file pointer at offset 0:
		if (ftruncate($fd, 0) === false) {
			return false;
		}
		// Write to file:
		if ((fwrite($fd, $enc)) === false) {
			return false;
		}
		fflush($fd);
		return true;
	}

	private static function
	write ($filename, $iv_size, $user_key, $data)
	{
		if (file_exists(self::$config->cache_dir) === false) {
			mkdir(self::$config->cache_dir, 0700, false);
		}
		umask(0177);	// Create all files -rw------

		// There is a slight race condition here with the cache cleanup
		// processes: if we fopen() a stale file, the cleanup process could
		// unlink it before we manage to modify its mtime by calling
		// ftruncate(). The worst that can happen is a potential cache miss on
		// the next lookup. That's not worth serializing cache access for.
		if (($fd = fopen($filename, 'a')) === false) {
			return false;
		}
		if (flock($fd, LOCK_EX) === false) {
			fclose($fd);
			return false;
		}
		$result = self::writefd($fd, $iv_size, $user_key, $data);

		flock($fd, LOCK_UN);
		fclose($fd);

		return $result;
	}

	private static function
	enc ($data, $iv_size, $user_key)
	{
		if (($iv = mcrypt_create_iv($iv_size, MCRYPT_RAND)) === false) {
			return false;
		}
		// The key is a salted hash of the user's password;
		// this salt is not very random, but "good enough";
		// MD5-hash it to a binary value so its length is constant and known: 16 bytes:
		$salt = md5(uniqid('', true), true);
		$key = sha1($salt . $user_key . 'webfolders', true);

		// Prepend the IV and the password salt to the data; both are not secret:
		return $iv . $salt . mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, $iv);
	}

	private static function
	dec ($data, $iv_size, $user_key)
	{
		// Binary MD5 hash is 16 bytes long:
		$salt_size = 16;

		// Get the IV and the salt from the front of the encrypted data:
		if (strlen($data) < $iv_size + $salt_size) {
			return false;
		}
		$iv = substr($data, 0, $iv_size);
		$salt = substr($data, $iv_size, $salt_size);
		$key = sha1($salt . $user_key . 'webfolders', true);

		return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, substr($data, $iv_size + $salt_size), MCRYPT_MODE_CBC, $iv);
	}

	public static function
	get ($function, $args = array(), $auth, $uri, $timeout)
	{
		// $user_key is a unique per-user value, used to save lookup
		// results under that user's identifier.
		if (self::$config->cache_use === false) {
			return call_user_func_array($function, $args);
		}
		if (extension_loaded('mcrypt') === false) {
			return call_user_func_array($function, $args);
		}
		if (($iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC)) === false) {
			return call_user_func_array($function, $args);
		}
		$filename = self::filename($auth, $function, $uri);

		// The key we use to encrypt the data:
		$user_key = $auth->pass . $function . $uri->uriFull();

		if (self::read($filename, $iv_size, $user_key, $timeout, $data)) {
			return $data;
		}
		// Else run the function, store the result in the cache:
		$data = call_user_func_array($function, $args);
		self::write($filename, $iv_size, $user_key, $data);
		return $data;
	}

	public static function
	destroy ($function, $args = array(), $auth, $uri)
	{
		if (self::$config->cache_use === false) {
			return;
		}
		// Try to get blocking lock on cache clean semaphore file:
		// If this call fails, just steam ahead regardless:
		$fd = self::clean_semaphore_open(true);
		@unlink(self::filename($auth, $function, $uri));
		if ($fd) self::clean_semaphore_close($fd);
	}

	public static function
	clean ()
	{
		if (self::$config->cache_use === false) {
			return false;
		}
		// Must acquire the cache clean semaphore, else assume
		// that another cleanup thread is already running:
		if (($fd = self::clean_semaphore()) === false) {
			return false;
		}
		// Remove files older than 60 seconds:
		if (($dir = opendir(self::$config->cache_dir)) === false) {
			self::clean_semaphore_close($fd);
			return false;
		}
		while (($entry = readdir($dir)) !== false) {
			if ($entry == '.' || $entry == '..' || $entry == 'last_cleaned') {
				continue;
			}
			$file = self::$config->cache_dir."/$entry";
			if ((time() - filemtime($file)) > 60) {
				unlink($file);
			}
		}
		closedir($dir);
		// ftruncate() updates the mtime on the semaphore file:
		ftruncate($fd, 0);
		self::clean_semaphore_close($fd);
		return true;
	}

	private static function
	clean_semaphore ()
	{
		// Returns false if we are not eligible to clean (semaphore
		// file is locked, or timestamp too recent), else returns fd
		// of semaphore file.
		if (($fd = self::clean_semaphore_open(false)) === false) {
			return false;
		}
		// Get last modification time:
		if (($stat = fstat($fd)) === false) {
			self::clean_semaphore_close($fd);
			return false;
		}
		// File modification time too recent?
		if ((time() - $stat['mtime']) < 60) {
			self::clean_semaphore_close($fd);
			return false;
		}
		return $fd;
	}

	private static function
	clean_semaphore_open ($block)
	{
		// Returns the fd of the semaphore file in locked state if we
		// were able to acquire it, else false.

		// Try to open the semaphore file in append mode.
		// This can fail if there is no cache directory, in which case
		// we don't need to do cleanup anyway.
		if (($fd = @fopen(self::$config->cache_clean_semaphore, 'a')) === false) {
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

	private static function
	clean_semaphore_close ($fd)
	{
		flock($fd, LOCK_UN);
		fclose($fd);
	}
}
