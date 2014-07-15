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

namespace SambaDAV;

require_once dirname(dirname(__FILE__)).'/config/config.inc.php';
require_once 'class.smb.php';
require_once 'function.log.php';
require_once 'streamfilter.md5.php';
require_once 'class.propflags.php';
require_once 'class.smbprocess.php';

use Sabre\DAV;

class File extends DAV\FSExt\File
{
	private $share;		// Name of share
	private $vpath;		// Name of the directory this file is in
	private $fname;		// Name of the file itself
	private $mtime;		// Modification time (Unix timestamp)
	private $fsize;		// File size (bytes)
	private $flags;		// SMB flags
	private $parent;	// Parent object

	private $user;		// Login credentials
	private $pass;

	private $proc = null;	// Global storage, so that this object does not go out of scope when get() returns

	public function __construct ($server, $share, $vpath, $entry, $parent, $user, $pass)
	{
		$this->server = $server;
		$this->share = $share;
		$this->vpath = $vpath;
		$this->fname = $entry[0];
		$this->flags = new Propflags($entry[1]);
		$this->fsize = $entry[2];
		$this->mtime = $entry[3];
		$this->parent = $parent;

		$this->user = $user;
		$this->pass = $pass;
	}

	public function getName ()
	{
		return $this->fname;
	}

	public function setName ($name)
	{
		log_trace('setName "'.$this->pretty_name()."\" -> \"$name\"\n");
		switch (SMB::rename($this->user, $this->pass, $this->server, $this->share, $this->vpath, $this->fname, $name)) {
			case SMB::STATUS_OK:
				$this->invalidate_parent();
				$this->fname = $name;
				return true;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound();
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden();
		}
	}

	public function get ()
	{
		// NB: because we return a file resource, we must ensure that
		// the proc object stays alive after we leave this function.
		// So we use a global class variable to store it.
		// It's not pretty, but it makes real streaming possible.
		log_trace('get "'.$this->pretty_name()."\"\n");

		$this->proc = new \SambaDAV\SMBClient\Process($this->user, $this->pass);

		switch (SMB::get($this->server, $this->share, $this->vpath, $this->fname, $this->proc)) {
			case SMB::STATUS_OK: return $this->proc->getOutputStreamHandle();
			case SMB::STATUS_NOTFOUND: $this->proc = null; $this->exc_notfound();
			case SMB::STATUS_SMBCLIENT_ERROR: $this->proc = null; $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->proc = null; $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->proc = null; $this->exc_forbidden();
		}
	}

	public function put ($data)
	{
		log_trace('put "'.$this->pretty_name()."\"\n");
		switch (SMB::put($this->user, $this->pass, $this->server, $this->share, $this->vpath, $this->fname, $data, $md5)) {
			case SMB::STATUS_OK:
				$this->invalidate_parent();
				return ($md5 === NULL) ? NULL : "\"$md5\"";

			case SMB::STATUS_NOTFOUND: $this->exc_notfound();
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden();
		}
	}

	public function putRange ($data, $offset)
	{
		// Sorry bro, smbclient is not that advanced:
		// Override the inherited method from the base class:
		log_trace('EXCEPTION: putRange "'.$this->pretty_name()."\" not implemented\n");
		throw new DAV\Exception\NotImplemented("PutRange() not available due to limitations of smbclient");
	}

	public function getETag ()
	{
		log_trace('getETag "'.$this->pretty_name()."\"\n");
		// Don't bother if the file is too large:
		if ($this->fsize > ETAG_SIZE_LIMIT) {
			return null;
		}
		// Create a process in $this->proc, use its read fd:
		if (!is_resource($fd = $this->get())) {
			return $this->proc = null;
		}
		// Get the eTag by streaming the file and inserting an md5 streamfilter:
		stream_filter_register('md5sum', 'md5sum_filter');
		$md5_filter = stream_filter_append($fd, 'md5sum');
		while (fread($fd, 5000000));
		stream_filter_remove($md5_filter);
		$md5 = md5s_get_hash();
		$this->proc = null;
		return "\"$md5\"";
	}

	public function getContentType ()
	{
		return NULL;
	}

	public function getSize ()
	{
		return $this->fsize;
	}

	public function getLastModified ()
	{
		return $this->mtime;
	}

	public function getIsHidden ()
	{
		return $this->flags->get('H');
	}

	public function getIsReadonly ()
	{
		return $this->flags->get('R');
	}

	public function getWin32Props ()
	{
		return $this->flags->to_win32();
	}

	public function updateProperties ($mutations)
	{
		log_trace('updateProperties: "'.$this->pretty_name()."\"\n");

		$new_flags = clone $this->flags;
		$invalidate = false;

		foreach ($mutations as $key => $val) {
			switch ($key) {
				case '{urn:schemas-microsoft-com:}Win32CreationTime':
				case '{urn:schemas-microsoft-com:}Win32LastAccessTime':
				case '{urn:schemas-microsoft-com:}Win32LastModifiedTime':
					// Silently ignore these;
					// smbclient has no 'touch' command or similar:
					break;

				case '{urn:schemas-microsoft-com:}Win32FileAttributes':
					// ex. '00000000', '00000020'
					// Decode into array of flags:
					$new_flags->from_win32($val);
					break;

				case '{DAV:}ishidden':
					$new_flags->set('H', $val);
					break;

				case '{DAV:}isreadonly':
					$new_flags->set('R', $val);
					break;

				default:
					// TODO: logging!
					break;
			}
		}
		// ->diff() returns an array with zero, one or two strings: the
		// modeflags necessary to set and unset the proper flags with
		// smbclient's setmode command:
		foreach ($this->flags->diff($new_flags) as $modeflag) {
			switch (SMB::setMode($this->user, $this->pass, $this->server, $this->share, $this->vpath, $this->fname, $modeflag)) {
				case SMB::STATUS_OK:
					$invalidate = true;
					continue;

				case SMB::STATUS_NOTFOUND: $this->exc_notfound();
				case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
				case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
				case SMB::STATUS_INVALID_NAME: $this->exc_forbidden();
			}
		}
		if ($invalidate) {
			// Parent must do a new 'ls' to refresh flags:
			$this->invalidate_parent();
			$this->flags = $this->new_flags;
		}
		return true;
	}

	public function delete ()
	{
		log_trace('delete "'.$this->pretty_name()."\"\n");
		switch (SMB::rm($this->user, $this->pass, $this->server, $this->share, $this->vpath, $this->fname)) {
			case SMB::STATUS_OK:
				$this->invalidate_parent();
				return true;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound();
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden();
		}
	}

	private function invalidate_parent ()
	{
		if ($this->parent !== false) {
			$this->parent->cache_destroy();
		}
	}

	private function pretty_name ()
	{
		// Return a string with the "pretty name" of this resource: no double slashes, etc:
		$str = "//{$this->server}";
		if (substr($str, -1, 1) != '/') $str .= '/'; $str .= (($this->share[0] == '/') ? substr($this->share, 1) : $this->share);
		if (substr($str, -1, 1) != '/') $str .= '/'; $str .= (($this->vpath[0] == '/') ? substr($this->vpath, 1) : $this->vpath);
		if (substr($str, -1, 1) != '/') $str .= '/'; $str .= (($this->fname[0] == '/') ? substr($this->fname, 1) : $this->fname);
		return $str;
	}

	private function exc_forbidden ()
	{
		// Only one type of Forbidden error right now: invalid filename or pathname
		$m = 'Forbidden: invalid pathname or filename';
		log_trace("EXCEPTION: $m\n");
		throw new DAV\Exception\Forbidden($m);
	}

	private function exc_notfound ()
	{
		$m = 'Not found: "'.$this->pretty_name().'"';
		log_trace("EXCEPTION: $m\n");
		throw new DAV\Exception\NotFound($m);
	}

	private function exc_smbclient ()
	{
		$m = 'smbclient error';
		log_trace('EXCEPTION: "'.$this->pretty_name()."\": $m\n");
		throw new DAV\Exception($m);
	}

	private function exc_unauthenticated ()
	{
		$m = "\"{$this->user}\" not authenticated for \"".$this->pretty_name().'"';
		log_trace("EXCEPTION: $m\n");
		throw new DAV\Exception\NotAuthenticated($m);
	}
}
