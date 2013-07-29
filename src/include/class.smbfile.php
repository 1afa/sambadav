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
require_once 'function.smb.php';
require_once 'function.log.php';
require_once 'streamfilter.md5.php';

use Sabre\DAV;

class SMBFile extends DAV\FSExt\File
{
	private $share;		// Name of share
	private $vpath;		// Name of the directory this file is in
	private $fname;		// Name of the file itself
	private $mtime;		// Modification time (Unix timestamp)
	private $fsize;		// File size (bytes)
	private $flags;		// SMB flags
	private $parent_path;

	private $user;		// Login credentials
	private $pass;

	private $proc;		// Global storage, so that these objects
	private $fds;		// don't go out of scope when get() returns

	function __construct ($server, $share, $vpath, $entry, $parent_path, $user, $pass)
	{
		$this->server = $server;
		$this->share = $share;
		$this->vpath = $vpath;
		$this->fname = $entry[0];
		$this->flags = $entry[1];
		$this->fsize = $entry[2];
		$this->mtime = $entry[3];
		$this->parent_path = $parent_path;

		$this->user = $user;
		$this->pass = $pass;
	}

	function getName ()
	{
		return $this->fname;
	}

	function setName ($name)
	{
		log_trace('setName "'.$this->pretty_name()."\" -> \"$name\"\n");
		switch (smb_rename($this->user, $this->pass, $this->server, $this->share, $this->vpath, $this->fname, $name)) {
			case STATUS_OK:
				$this->invalidate_parent();
				$this->fname = $name;
				return TRUE;

			case STATUS_NOTFOUND: $this->exc_notfound();
			case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case STATUS_INVALID_NAME: $this->exc_forbidden();
		}
	}

	function get ()
	{
		// NB: because we return a file resource, we must ensure that the proc and fds object
		// stay alive after we leave this function. So we use global class variables to store them.
		// It's not pretty, but it makes real streaming possible.
		log_trace('get "'.$this->pretty_name()."\"\n");
		smb_proc_close($this->proc, $this->fds);
		return $this->_get($this->proc, $this->fds);
	}

	private function _get (&$proc, &$fds)
	{
		// Helper function, do the actual get() with the resource vars passed by caller:
		switch (smb_get($this->user, $this->pass, $this->server, $this->share, $this->vpath, $this->fname, $proc, $fds)) {
			case STATUS_OK: return $fds[5];
			case STATUS_NOTFOUND: smb_proc_close($proc, $fds); $this->exc_notfound();
			case STATUS_SMBCLIENT_ERROR: smb_proc_close($proc, $fds); $this->exc_smbclient();
			case STATUS_UNAUTHENTICATED: smb_proc_close($proc, $fds); $this->exc_unauthenticated();
			case STATUS_INVALID_NAME: $this->exc_forbidden();
		}
	}

	function put ($data)
	{
		log_trace('put "'.$this->pretty_name()."\"\n");
		switch (smb_put($this->user, $this->pass, $this->server, $this->share, $this->vpath, $this->fname, $data, $md5)) {
			case STATUS_OK:
				$this->invalidate_parent();
				return ($md5 === NULL) ? NULL : "\"$md5\"";

			case STATUS_NOTFOUND: $this->exc_notfound();
			case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case STATUS_INVALID_NAME: $this->exc_forbidden();
		}
	}

	function putRange($data, $offset)
	{
		// Sorry bro, smbclient is not that advanced:
		// Override the inherited method from the base class:
		log_trace('EXCEPTION: putRange "'.$this->pretty_name()."\" not implemented\n");
		throw new DAV\Exception\NotImplemented("PutRange() not available due to limitations of smbclient");
	}

	function getETag ()
	{
		log_trace('getETag "'.$this->pretty_name()."\"\n");
		// Don't bother if the file is too large:
		if ($this->fsize > ETAG_SIZE_LIMIT) {
			return NULL;
		}
		// Do not use the global resource vars, but our own private ones:
		if (!is_resource($this->_get($proc, $fds))) {
			smb_proc_close($proc, $fds);
			return NULL;
		}
		// Get the eTag by streaming the file and inserting an md5 streamfilter:
		stream_filter_register('md5sum', 'md5sum_filter');
		$md5_filter = stream_filter_append($fds[5], 'md5sum');
		while (fread($fds[5], 5000000));
		stream_filter_remove($md5_filter);
		smb_proc_close($proc, $fds);
		$md5 = md5s_get_hash();
		return "\"$md5\"";
	}

	function getContentType ()
	{
		return NULL;
	}

	function getSize ()
	{
		return $this->fsize;
	}

	function getLastModified ()
	{
		return $this->mtime;
	}

	function getIsHidden ()
	{
		// Look at the 'H' flag in the parsed Samba attributes:
		if (FALSE($this->flags)) return FALSE;
		return (FALSE(strpos($this->flags, 'H'))) ? 0 : 1;
	}

	function getIsReadonly ()
	{
		// Look at the 'R' flag in the parsed Samba attributes:
		if (FALSE($this->flags)) return FALSE;
		return (FALSE(strpos($this->flags, 'R'))) ? 0 : 1;
	}

	function getIsArchive ()
	{
		// Look at the 'A' flag in the parsed Samba attributes:
		if (FALSE($this->flags)) return FALSE;
		return (FALSE(strpos($this->flags, 'A'))) ? 0 : 1;
	}

	function getIsSystem ()
	{
		// Look at the 'S' flag in the parsed Samba attributes:
		if (FALSE($this->flags)) return FALSE;
		return (FALSE(strpos($this->flags, 'S'))) ? 0 : 1;
	}

	function getWin32Props ()
	{
		if (FALSE($h = $this->getIsHidden())) return FALSE;
		if (FALSE($a = $this->getIsArchive())) return FALSE;
		if (FALSE($r = $this->getIsReadonly())) return FALSE;
		if (FALSE($s = $this->getIsSystem())) return FALSE;

		return win32_propstring($r, $h, $s, FALSE, $a);
	}

	function updateProperties ($mutations)
	{
		log_trace('updateProperties: "'.$this->pretty_name()."\"\n");

		$props_on = array();
		$props_off = array();
		$modeflags = array();
		$invalidate = FALSE;

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
					if (FALSE($newflags = win32_propstring_decode($val))) {
						continue;
					}
					// Get current flag values to find out what changed:
					$curflags = array
						( 'r' => $this->getIsReadonly()
						, 'h' => $this->getIsHidden()
						, 's' => $this->getIsSystem()
						, 'a' => $this->getIsArchive()
						) ;

					// Loop over $curflags to purposefully ignore the 'd' flag
					// that's present in $newflags: the 'd' flag is not settable
					// by smbclient and will return an error (not that we should
					// ever see it toggle). The only valid flags for smbclient's
					// `setmode` command  are +-rhsa:
					foreach ($curflags as $flag => $bool)
					{
						// Skip this flag if nothing changed:
						if ($newflags[$flag] === $curflags[$flag]) {
							continue;
						}
						if ($bool === 0) {
							$props_on[$flag] = TRUE;
						}
						else {
							$props_off[$flag] = TRUE;
						}
					}
					break;

				case '{DAV:}ishidden':
					if ($val == 1) $props_on['h'] = TRUE;
					if ($val == 0) $props_off['h'] = TRUE;
					break;

				case '{DAV:}isreadonly':
					if ($val == 1) $props_on['r'] = TRUE;
					if ($val == 0) $props_off['r'] = TRUE;
					break;

				default:
					// TODO: logging!
					break;
			}
		}
		// Set modeflags in two steps: first batch the flags that are
		// turned off, then batch the flags that are turned on:
		if (count($props_off)) {
			$modeflags[] = '-' . implode(array_keys($props_off));
		}
		if (count($props_on)) {
			$modeflags[] = '+' . implode(array_keys($props_on));
		}
		foreach ($modeflags as $modeflag) {
			switch (smb_setmode($this->user, $this->pass, $this->server, $this->share, $this->vpath, $this->fname, $modeflag)) {
				case STATUS_OK:
					$invalidate = TRUE;
					continue;

				case STATUS_NOTFOUND: $this->exc_notfound();
				case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
				case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
				case STATUS_INVALID_NAME: $this->exc_forbidden();
			}
		}
		if ($invalidate) {
			// Parent must do a new 'ls' to refresh flags:
			$this->invalidate_parent();
		}
		return TRUE;
	}

	function delete ()
	{
		log_trace('delete "'.$this->pretty_name()."\"\n");
		switch (smb_rm($this->user, $this->pass, $this->server, $this->share, $this->vpath, $this->fname)) {
			case STATUS_OK:
				$this->invalidate_parent();
				return TRUE;

			case STATUS_NOTFOUND: $this->exc_notfound();
			case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case STATUS_INVALID_NAME: $this->exc_forbidden();
		}
	}

	private function invalidate_parent ()
	{
		if (FALSE($server_tree = server_get_tree())
		 || FALSE($parent = $server_tree->getNodeForPath($this->parent_path))) {
			return;
		}
		$server_tree->markDirty($this->parent_path);
		$parent->cache_destroy();
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
