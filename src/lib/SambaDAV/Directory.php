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

use Sabre\DAV;

class Directory extends DAV\FSExt\Directory
{
	private $server = false;	// server that the share is on
	private $share = false;		// name of the share
	private $vpath = false;		// virtual path from root of share
	private $entries = false;
	private $flags = false;		// SMB flags
	private $parent = false;
	private $user = false;		// login credentials
	private $pass = false;
	private $userhome_server = false;
	private $userhome_share = false;

	public function __construct ($server, $share, $path, $parent, $smbflags, $user, $pass)
	{
		$this->user = $user;
		$this->pass = $pass;
		$this->server = $server;
		$this->share = $share;
		$this->flags = new \SambaDAV\Propflags($smbflags);
		$this->vpath = ($path === false) ? '/' : $path;
		$this->parent = $parent;
	}

	public function getChildren ()
	{
		$children = array();

		// If in root folder, show master shares list:
		if ($this->server === false) {
			foreach ($this->global_root_entries() as $entry) {
				$children[] = new Directory($entry[0], $entry[1], false, $this, 'D', $this->user, $this->pass);
			}
			return $children;
		}
		// If in root folder for given server, fetch all allowed shares for that server:
		if ($this->share === false) {
			foreach ($this->server_root_entries() as $entry) {
				$children[] = new Directory($this->server, $entry, false, $this, 'D', $this->user, $this->pass);
			}
			return $children;
		}
		// Else, open share, produce listing:
		if ($this->entries === false) {
			$this->get_entries();
		}
		foreach ($this->entries as $entry) {
			if ($entry[0] === '..' || $entry[0] === '.') {
				continue;
			}
			$children[] = $this->getChild($entry[0]);
		}
		return $children;
	}

	public function getChild ($name)
	{
		Log::trace('getChild "'.$this->pretty_name()."$name\"\n");

		// Are we a folder in the root dir?
		if ($this->server === false) {
			foreach ($this->global_root_entries() as $displayname => $entry) {
				if ($name === $displayname) {
					return new Directory($entry[0], $entry[1], false, $this, 'D', $this->user, $this->pass);
				}
			}
			$this->exc_notfound($name);
			return false;
		}
		// We have a server, but do we have a share?
		if ($this->share === false) {
			if (in_array($name, $this->server_root_entries())) {
				return new Directory($this->server, $name, false, $this, 'D', $this->user, $this->pass);
			}
			$this->exc_notfound($name);
			return false;
		}
		// We have a server and a share, get entries:
		if ($this->entries === false) {
			$this->get_entries();
		}
		if ($this->entries !== false) {
			foreach ($this->entries as $entry) {
				if ($entry[0] !== $name) {
					continue;
				}
				if (strpos($entry[1], 'D') === false) {
					return new File($this->server, $this->share, $this->vpath, $entry, $this, $this->user, $this->pass);
				}
				return new Directory($this->server, $this->share, $this->vpath.'/'.$entry[0], $this, $entry[1], $this->user, $this->pass);
			}
		}
		$this->exc_notfound($this->pretty_name().$name);
		return false;
	}

	public function createDirectory ($name)
	{
		Log::trace('createDirectory "'.$this->pretty_name()."$name\"\n");

		// Cannot create directories in the root:
		if ($this->server === false || $this->share === false) {
			$this->exc_forbidden('Cannot create shares in root');
		}
		switch (SMB::mkdir($this->user, $this->pass, $this->server, $this->share, $this->vpath, $name)) {
			case SMB::STATUS_OK:
				// Invalidate entries cache:
				$this->cache_destroy();
				return true;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	public function createFile ($name, $data = NULL)
	{
		Log::trace('createFile "'.$this->pretty_name()."$name\"\n");

		if ($this->server === false || $this->share === false) {
			$this->exc_forbidden('Cannot create files in root');
		}
		switch (SMB::put($this->user, $this->pass, $this->server, $this->share, $this->vpath, basename($name), $data, $md5)) {
			case SMB::STATUS_OK:
				// Invalidate entries cache:
				$this->cache_destroy();
				return ($md5 === NULL) ? NULL : "\"$md5\"";

			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	public function childExists ($name)
	{
		// Are we the global root?
		if ($this->server === false) {
			foreach ($this->global_root_entries() as $displayname => $entry) {
				if ($name === $displayname) {
					return true;
				}
			}
			return false;
		}
		// Are we a server root?
		if ($this->share === false) {
			return (in_array($name, $this->server_root_entries()));
		}
		if ($this->entries === false) {
			$this->get_entries();
		}
		foreach ($this->entries as $entry) {
			if ($name === $entry[0]) {
				return true;
			}
		}
		return false;
	}

	public function getName ()
	{
		if ($this->server === false) return '/';
		if ($this->share === false) return $this->server;
		if ($this->vpath === false || $this->vpath === '/') return $this->share;
		return basename($this->vpath);
	}

	public function setName ($name)
	{
		Log::trace('setName "'.$this->pretty_name()."\" -> \"$name\"\n");

		if ($this->server === false || $this->share === false || $this->vpath === '' || $this->vpath === '/') {
			$this->exc_notimplemented('cannot rename root folders');
		}
		switch (SMB::rename($this->user, $this->pass, $this->server, $this->share, dirname($this->vpath), basename($this->vpath), $name)) {
			case SMB::STATUS_OK:
				$this->cache_destroy();
				$this->invalidate_parent();
				$this->vpath = basename($this->vpath)."/$name";
				return true;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
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

	public function getQuotaInfo ()
	{
		Log::trace('getQuotaInfo "'.$this->pretty_name()."\"\n");

		// NB: Windows 7 uses/needs this method. Must return array.
		// We refuse to do the actual lookup, because:
		// - smbclient `du` can only give us per-share numbers, not
		//   per-directory as this function requires;
		// - Windows 7 makes a LOT of these calls, and honoring them
		//   slows things down enormously;
		// - Windows 7 appears to use a recursive ls to determine
		//   disk usage if it can't get direct quota numbers;
		// - Windows 7 does not appear to actually *use* the quota
		//   numbers for printing usage pie charts and things.
		static $quota = NULL;

		// Can we return a cached value?
		if ($quota !== NULL) {
			return $quota;
		}
		// If we're a subdir, make SabreDAV query the root:
		if ($this->server === false || $this->share === false || $this->vpath !== '/') {
			return ($quota = false);
		}
		// Get results from disk cache if available and fresh:
		$quota = Cache::get('\SambaDAV\SMB::du', array($this->user, $this->pass, $this->server, $this->share), $this->user, $this->pass, 20);
		if (is_array($quota)) {
			return $quota;
		}
		switch ($quota) {
			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
		}
		return false;
	}

	public function delete ()
	{
		Log::trace('delete "'.$this->pretty_name()."\"\n");

		if ($this->server === false || $this->share === false || $this->vpath === '' || $this->vpath === '/') {
			$this->exc_notimplemented('cannot delete root folders');
		}
		switch (SMB::rmdir($this->user, $this->pass, $this->server, $this->share, dirname($this->vpath), basename($this->vpath))) {
			case SMB::STATUS_OK:
				$this->cache_destroy();
				$this->invalidate_parent();
				return true;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	public function updateProperties ($mutations)
	{
		// Stub function, see \SambaDAV\File::updateProperties() for
		// more details.
		// By default, Sabre wants to save these properties in a file
		// in the root called .sabredav, but that location is not
		// writable in our setup. Silently ignore for now.

		// In \SambaDAV\File::updateProperties(), we use smbclient's
		// `setmode` command to set file flags. Unfortunately, that
		// command only appears to work for files, not directories. So
		// even though we know how to decipher the Win32 propstring
		// we're given, we have no way of setting the flags in the
		// backend.

		return true;
	}

	public function cache_destroy ()
	{
		Cache::destroy('\SambaDAV\SMB::ls', array($this->user, $this->pass, $this->server, $this->share, $this->vpath), $this->user);
		$this->entries = false;
	}

	public function setUserhome ($url)
	{
		$this->userhome_server = false;
		$this->userhome_share = false;

		// $url must have the form \\server or \\server\share.
		if (!is_string($url) || strlen($url) < 3 || substr($url, 0, 2) !== '\\\\') {
			return false;
		}
		$url = substr($url, 2);

		// No share, only server:
		if (($pos = strpos($url, '\\')) === false) {
			$this->userhome_server = $url;
			return true;
		}
		// No server, only share? Error:
		if ($pos === 0) {
			return false;
		}
		$share = substr($url, $pos + 1);

		// Share has extra slashes?
		if (strpos($share, '\\') !== false) {
			return false;
		}
		$this->userhome_server = substr($url, 0, $pos);
		$this->userhome_share = $share;
		return true;
	}

	private function get_entries ()
	{
		// Get listing from disk cache if available and fresh:
		$this->entries = Cache::get('\SambaDAV\SMB::ls', array($this->user, $this->pass, $this->server, $this->share, $this->vpath), $this->user, $this->pass, 5);
		if (is_array($this->entries)) {
			return;
		}
		switch ($this->entries) {
			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
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
		// Guaranteed to end with a slash.
		if ($this->server === false) return '/';
		$str = "//{$this->server}";
		if ($this->share === false) return $str;
		if (substr($str, -1, 1) != '/') $str .= '/'; if ($this->share === false) return $str; $str .= (($this->share[0] == '/') ? substr($this->share, 1) : $this->share);
		if (substr($str, -1, 1) != '/') $str .= '/'; if ($this->vpath === false) return $str; $str .= (($this->vpath[0] == '/') ? substr($this->vpath, 1) : $this->vpath);
		if (substr($str, -1, 1) != '/') $str .= '/';
		return $str;
	}

	private function global_root_entries ()
	{
		global $share_root;
		global $share_extra;

		// structure:
		// $entries = array('name-of-root-folder' => array('server', 'share-on-that-server'))
		$entries = array();

		foreach ($share_root as $entry)
		{
			$server = (isset($entry[0])) ? $entry[0] : false;
			$share  = (isset($entry[1])) ? $entry[1] : false;

			if ($server === false) {
				continue;
			}
			if ($share !== false && $share !== null && $share !== '') {
				$entries[$share] = array($server, $share);
				continue;
			}
			// Just the server name given; autodiscover all shares on this server:
			if (!is_array($shares = Cache::get('\SambaDAV\SMB::getShares', array($server, $this->user, $this->pass), $this->user, $this->pass, 15))) {
				// TODO: throw an exception?
				// switch ($shares) {
				// 	case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
				// 	case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
				// 	case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
				// }
				continue;
			}
			foreach ($shares as $share) {
				$entries[$share] = array($server, $share);
			}
		}
		// Servers from $shares_extra get a folder with the name of the *server*:
		foreach ($share_extra as $entry) {
			$entries[$entry[0]] = array($entry[0], false);
		}
		// The user's home directory gets a folder with the name of the *user*:
		// User can be false if we allow anonymous logins, in which case ignore:
		if ($this->user !== false && $this->userhome_server !== false) {
			if ($this->userhome_share === false) {
				$entries[$this->user] = array($this->userhome_server, $this->user);
			}
			else {
				$entries[$this->userhome_share] = array($this->userhome_server, $this->userhome_share);
			}
		}
		return $entries;
	}

	private function server_root_entries ()
	{
		global $share_root;
		global $share_extra;

		$entries = array();

		// Shares in the global root belonging to this server
		// also show up in the server's own subdir:
		foreach ($share_root as $entry) {
			list($server, $share) = $entry;
			if ($server != $this->server) {
				continue;
			}
			$entries[$share] = 1;
		}
		foreach ($share_extra as $entry) {
			list($server, $share) = $entry;
			if ($server != $this->server) {
				continue;
			}
			if ($share !== false && $share !== null && $share !== '') {
				$entries[$share] = 1;
				continue;
			}
			// Only our server name given in $share_extra;
			// this means: autodiscover and use all the shares on this server:
			if (!is_array($shares = Cache::get('\SambaDAV\SMB::getShares', array($this->server, $this->user, $this->pass), $this->user, $this->pass, 15))) {
				// TODO: throw an exception?
				// switch ($shares) {
				// 	case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
				// 	case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
				// 	case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
				// }
				continue;
			}
			foreach ($shares as $share) {
				$entries[$share] = 1;
			}
		}
		// User's home share is on this server?
		if ($this->user !== false && $this->userhome_server && $this->userhome_server == $this->server) {
			if ($this->userhome_share === false) {
				$entries[$this->user] = 1;
			}
			else {
				$entries[$this->userhome_share] = 1;
			}
		}
		return array_keys($entries);
	}

	private function exc_smbclient ()
	{
		$m = 'smbclient error';
		Log::trace('EXCEPTION: "'.$this->pretty_name()."\": $m\n");
		throw new DAV\Exception($m);
	}

	private function exc_forbidden ($msg)
	{
		$m = "Forbidden: $msg";
		Log::trace('EXCEPTION: "'.$this->pretty_name()."\": $m\n");
		throw new DAV\Exception\Forbidden($m);
	}

	private function exc_notfound ($name)
	{
		$m = "Not found: \"$name\"";
		Log::trace("EXCEPTION: $m\n");
		throw new DAV\Exception\NotFound($m);
	}

	private function exc_unauthenticated ()
	{
		$m = '"'.$this->user.'" not authenticated for "'.$this->pretty_name()."\"";
		Log::trace("EXCEPTION: $m\n");
		throw new DAV\Exception\NotAuthenticated($m);
	}

	private function exc_notimplemented ($msg)
	{
		$m = "Not implemented: $msg";
		Log::trace('EXCEPTION: "'.$this->pretty_name()."\": $m\n");
		throw new DAV\Exception\Forbidden($m);
	}
}
