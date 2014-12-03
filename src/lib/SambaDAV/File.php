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

use Sabre\DAV;

class File extends DAV\FSExt\File
{
	private $uri;
	private $etag = null;
	private $size;		// File size (bytes)
	private $flags;		// SMB flags
	private $mtime;		// Modification time (Unix timestamp)
	private $parent;	// Parent object

	private $auth;
	private $config;
	private $log;
	private $smb;

	private $proc = null;	// Global storage, so that this object does not go out of scope when get() returns

	public function
	__construct ($auth, $config, $log, $smb, $uri, $parent, $size, $smbflags, $mtime)
	{
		$this->uri = $uri;
		$this->flags = new Propflags($smbflags);
		$this->size = $size;
		$this->mtime = $mtime;
		$this->parent = $parent;

		$this->auth = $auth;
		$this->config = $config;
		$this->log = $log;
		$this->smb = $smb;
	}

	public function
	getName ()
	{
		return $this->uri->name();
	}

	public function
	setName ($name)
	{
		$this->log->trace("File::setName '%s' -> '%s'\n", $this->uri->uriFull(), $name);
		switch ($this->smb->rename($this->uri, $name)) {
			case SMB::STATUS_OK:
				$this->invalidate_parent();
				$this->uri->rename($name);
				return true;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound();
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden();
		}
	}

	public function
	get ()
	{
		// NB: because we return a file resource, we must ensure that
		// the proc object stays alive after we leave this function.
		// So we use a global class variable to store it.
		// It's not pretty, but it makes real streaming possible.
		$this->log->trace("File::get '%s'\n", $this->uri->uriFull());

		$this->proc = new \SambaDAV\SMBClient\Process($this->auth, $this->config, $this->log);

		switch ($this->smb->get($this->uri, $this->proc)) {
			case SMB::STATUS_OK: return $this->proc->getOutputStreamHandle();
			case SMB::STATUS_NOTFOUND: $this->proc = null; $this->exc_notfound();
			case SMB::STATUS_SMBCLIENT_ERROR: $this->proc = null; $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->proc = null; $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->proc = null; $this->exc_forbidden();
		}
	}

	public function
	put ($data)
	{
		$this->log->trace("File::put '%s'\n", $this->uri->uriFull());
		switch ($this->smb->put($this->uri, $data, $md5)) {
			case SMB::STATUS_OK:
				$this->invalidate_parent();
				$this->etag = ($md5 === null) ? null : "\"$md5\"";
				return $this->etag;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound();
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden();
		}
	}

	public function
	putRange ($data, $offset)
	{
		// Sorry bro, smbclient is not that advanced:
		// Override the inherited method from the base class:
		$this->log->trace('EXCEPTION: putRange "'.$this->uri->uriFull()."\" not implemented\n");
		throw new DAV\Exception\NotImplemented("PutRange() not available due to limitations of smbclient");
	}

	public function
	getETag ()
	{
		$this->log->trace("File::getETag '%s'\n", $this->uri->uriFull());

		if ($this->etag !== null) {
			return $this->etag;
		}
		// Don't bother if the file is too large:
		if (isset($this->config->etag_size_limit)) {
			if ($this->size > $this->config->etag_size_limit) {
				return null;
			}
		}
		// Create a process in $this->proc, use its read fd:
		if (!is_resource($fd = $this->get())) {
			return $this->proc = null;
		}
		// Get the eTag by streaming the file and inserting an md5 streamfilter:
		$filterOutput = new MD5FilterOutput();
		stream_filter_register('md5sum', '\SambaDAV\MD5Filter');
		$filter = stream_filter_append($fd, 'md5sum', STREAM_FILTER_READ, $filterOutput);
		while (fread($fd, 5000000));
		stream_filter_remove($filter);
		$this->proc = null;
		$this->etag = sprintf('"%s"', $filterOutput->hash);
		return $this->etag;
	}

	public function
	getContentType ()
	{
		return null;
	}

	public function
	getSize ()
	{
		return $this->size;
	}

	public function
	getLastModified ()
	{
		return $this->mtime;
	}

	public function
	getIsHidden ()
	{
		return $this->flags->get('H');
	}

	public function
	getIsReadonly ()
	{
		return $this->flags->get('R');
	}

	public function
	getWin32Props ()
	{
		return $this->flags->toWin32();
	}

	public function
	updateProperties ($mutations)
	{
		$this->log->trace("File::updateProperties '%s'\n", $this->uri->uriFull());

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
					$new_flags->fromWin32($val);
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
			switch ($this->smb->setMode($this->uri, $modeflag)) {
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
			$this->flags = $new_flags;
		}
		return true;
	}

	public function
	delete ()
	{
		$this->log->trace("File::delete '%s'\n", $this->uri->uriFull());
		switch ($this->smb->rm($this->uri)) {
			case SMB::STATUS_OK:
				$this->invalidate_parent();
				return true;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound();
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden();
		}
	}

	private function
	invalidate_parent ()
	{
		if ($this->parent !== false) {
			$this->parent->cache_destroy();
		}
	}

	private function
	exc_forbidden ()
	{
		// Only one type of Forbidden error right now: invalid filename or pathname
		$m = 'Forbidden: invalid pathname or filename';
		$this->log->warn("EXCEPTION: $m\n");
		throw new DAV\Exception\Forbidden($m);
	}

	private function
	exc_notfound ()
	{
		$m = sprintf("Not found: '%s'", $this->uri->uriFull());
		$this->log->warn("EXCEPTION: $m\n");
		throw new DAV\Exception\NotFound($m);
	}

	private function
	exc_smbclient ()
	{
		$this->log->warn("EXCEPTION: '%s': smbclient error\n", $this->uri->uriFull());
		throw new DAV\Exception('smbclient error');
	}

	private function
	exc_unauthenticated ()
	{
		$m = sprintf("'%s' not authenticated for '%s'", $this->auth->user, $this->uri->uriFull());
		$this->log->warn("EXCEPTION: $m\n");
		throw new DAV\Exception\NotAuthenticated($m);
	}
}
