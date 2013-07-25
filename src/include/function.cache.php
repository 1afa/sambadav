<?php	// $Id: function.cache.php,v 1.20 2013/07/23 16:04:33 alfred Exp $
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

// This file is used as a lockfile and timestamp for the cache cleanup process,
// so that no cache writes can be done during a cleanup, and vice versa:
define('CACHE_CLEAN_SEMAPHORE', CACHE_DIR.'/last_cleaned');

function cache_filename ($user_name, $function, $args)
{
	return CACHE_DIR.'/'.sha1('webfolders'.$user_name.'webfolders'.$function.'webfolders'.join('', $args).'webfolders', false);
}

function cache_get ($function, $args = array(), $user_name, $user_pass, $timeout)
{
	// $user_key is a unique per-user value, used to save lookup
	// results under that user's identifier.
	if (FALSE(CACHE_USE)
	 || FALSE(extension_loaded('mcrypt'))
	 || FALSE($iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC))) {
		return call_user_func_array($function, $args);
	}
	$filename = cache_filename($user_name, $function, $args);
	if (cache_read($filename, $iv_size, $user_pass, $timeout, $data)) {
		return $data;
	}
	// Else run the function, store the result in the cache:
	$data = call_user_func_array($function, $args);
	cache_write($filename, $iv_size, $user_pass, $data);
	return $data;
}

function cache_read ($filename, $iv_size, $user_pass, $timeout, &$uns)
{
	if (FALSE($fd = @fopen($filename, 'r'))) {
		return FALSE;
	}
	if (FALSE(flock($fd, LOCK_SH))) {
		fclose($fd);
		return FALSE;
	}
	if (FALSE($stat = fstat($fd))
	 || (time() - $stat['mtime'] > $timeout)
	 || ($stat['size'] == 0)
	 || FALSE($data = fread($fd, $stat['size']))) {
		flock($fd, LOCK_UN);
		fclose($fd);
		return FALSE;
	}
	flock($fd, LOCK_UN);
	fclose($fd);
	if (FALSE($dec = cache_dec($data, $iv_size, $user_pass))
	 || FALSE($unc = gzuncompress($dec))
	 || FALSE($uns = unserialize($unc))) {
		return FALSE;
	}
	return TRUE;
}

function cache_write ($filename, $iv_size, $user_pass, $data)
{
	if (FALSE(file_exists(CACHE_DIR))) {
		mkdir(CACHE_DIR, 0700, FALSE);
	}
	umask(0177);	// Create all files -rw------

	// There is a slight race condition here with the cache cleanup
	// processes: if we fopen() a stale file, the cleanup process could
	// unlink it before we manage to modify its mtime by calling
	// ftruncate(). The worst that can happen is a potential cache miss on
	// the next lookup. That's not worth serializing cache access for.
	if (FALSE($fd = fopen($filename, 'a'))) {
		return FALSE;
	}
	if (FALSE(flock($fd, LOCK_EX))) {
		fclose($fd);
		return FALSE;
	}
	if (FALSE(ftruncate($fd, 0))) {
		flock($fd, LOCK_UN);
		fclose($fd);
		return FALSE;
	}
	if (FALSE($ser = serialize($data))
	 || FALSE($com = gzcompress($ser))
	 || FALSE($enc = cache_enc($com, $iv_size, $user_pass))
	 || FALSE(fwrite($fd, $enc))) {
		flock($fd, LOCK_UN);
		fclose($fd);
		return FALSE;
	}
	fflush($fd);
	flock($fd, LOCK_UN);
	fclose($fd);
	clearstatcache();
	return TRUE;
}

function cache_enc ($data, $iv_size, $user_pass)
{
	if (FALSE($iv = mcrypt_create_iv($iv_size, MCRYPT_RAND))) {
		return FALSE;
	}
	// The key is a salted hash of the user's password;
	// this salt is not very random, but "good enough";
	// MD5-hash it to a binary value so its length is constant and known: 16 bytes:
	$salt = md5(uniqid('', true), true);
	$key = sha1($salt . $user_pass . 'webfolders', true);

	// Prepend the IV and the password salt to the data; both are not secret:
	return $iv . $salt . mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, $iv);
}

function cache_dec ($data, $iv_size, $user_pass)
{
	// Binary MD5 hash is 16 bytes long:
	$salt_size = 16;

	// Get the IV and the salt from the front of the encrypted data:
	if (strlen($data) < $iv_size + $salt_size) {
		return FALSE;
	}
	$iv = substr($data, 0, $iv_size);
	$salt = substr($data, $iv_size, $salt_size);
	$key = sha1($salt . $user_pass . 'webfolders', true);

	return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, substr($data, $iv_size + $salt_size), MCRYPT_MODE_CBC, $iv);
}

function cache_destroy ($function, $args = array(), $user_name)
{
	if (FALSE(CACHE_USE)) {
		return;
	}
	// Try to get blocking lock on cache clean semaphore file:
	// If this call fails, just steam ahead regardless:
	$fd = cache_clean_semaphore_open(TRUE);
	@unlink(cache_filename($user_name, $function, $args));
	if ($fd) cache_clean_semaphore_close($fd);
}

function cache_clean ()
{
	if (FALSE(CACHE_USE)) {
		return FALSE;
	}
	// Must acquire the cache clean semaphore, else assume
	// that another cleanup thread is already running:
	if (FALSE($fd = cache_clean_semaphore())) {
		return FALSE;
	}
	// Remove files older than 60 seconds:
	if (FALSE($dir = opendir(CACHE_DIR))) {
		cache_clean_semaphore_close($fd);
		return FALSE;
	}
	while (!FALSE($entry = readdir($dir))) {
		if ($entry == '.' || $entry == '..' || $entry == 'last_cleaned') {
			continue;
		}
		$file = CACHE_DIR."/$entry";
		if ((time() - filemtime($file)) > 60) {
			unlink($file);
		}
	}
	closedir($dir);
	// ftruncate() updates the mtime on the semaphore file:
	ftruncate($fd, 0);
	cache_clean_semaphore_close($fd);
	clearstatcache();
	return TRUE;
}

function cache_clean_semaphore ()
{
	// Returns FALSE if we are not eligible to clean (semaphore
	// file is locked, or timestamp too recent), else returns fd
	// of semaphore file.
	if (FALSE($fd = cache_clean_semaphore_open(FALSE))) {
		return FALSE;
	}
	// File modification time too recent?
	if (FALSE($stat = fstat($fd)) || (time() - $stat['mtime']) < 60) {
		cache_clean_semaphore_close($fd);
		return FALSE;
	}
	return $fd;
}

function cache_clean_semaphore_open ($block)
{
	// Returns the fd of the semaphore file in locked state if we
	// were able to acquire it, else FALSE.
	for ($i = 0; $i < 2; $i++) {
		if (!FALSE($fd = @fopen(CACHE_CLEAN_SEMAPHORE, 'a'))) {
			break;
		}
		// Does dir need to be made? Do so and retry:
		if (FALSE(file_exists(CACHE_DIR)) && mkdir(CACHE_DIR, 0700, FALSE)) {
			continue;
		}
		return FALSE;
	}
	if ($block && flock($fd, LOCK_EX)) {
		return $fd;
	}
	else if (flock($fd, LOCK_EX | LOCK_NB, $wouldblock) && !$wouldblock) {
		return $fd;
	}
	fclose($fd);
	return FALSE;
}

function cache_clean_semaphore_close ($fd)
{
	flock($fd, LOCK_UN);
	fclose($fd);
}
