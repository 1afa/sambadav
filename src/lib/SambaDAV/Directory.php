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

class Directory extends DAV\FSExt\Directory
{
	public $uri = null;		// server that the share is on
	private $entries = false;
	private $flags;			// SMB flags
	private $mtime;
	private $parent = null;
	private $user = false;		// login credentials
	private $pass = false;
	private $userhome = null;

	public function __construct (URI $uri, $parent, $smbflags, $mtime, $user, $pass)
	{
		$this->uri = $uri;
		$this->user = $user;
		$this->pass = $pass;
		$this->flags = new Propflags($smbflags);
		$this->mtime = $mtime;
		$this->parent = $parent;
	}

	public function getChildren ()
	{
		Log::trace("Directory::getChildren '%s'\n", $this->uri->uriFull());

		$children = array();

		// If in root folder, show master shares list:
		if ($this->uri->isGlobalRoot()) {
			foreach ($this->global_root_entries() as $entry) {
				$children[] = new Directory(new URI($entry[0], $entry[1]), $this, 'D', null, $this->user, $this->pass);
			}
			return $children;
		}
		// If in root folder for given server, fetch all allowed shares for that server:
		if ($this->uri->isServerRoot()) {
			foreach ($this->server_root_entries() as $entry) {
				$uri = clone $this->uri;
				$uri->addParts($entry);
				$children[] = new Directory($uri, $this, 'D', null, $this->user, $this->pass);
			}
			return $children;
		}
		// Else, open share, produce listing:
		if ($this->entries === false) {
			$this->get_entries();
		}
		foreach ($this->entries as $entry) {
			if ($entry['name'] === '..' || $entry['name'] === '.') {
				continue;
			}
			$children[] = $this->getChild($entry['name']);
		}
		return $children;
	}

	public function getChild ($name)
	{
		Log::trace("Directory::getChild '%s' '%s'\n", $this->uri->uriFull(), $name);

		// Are we a folder in the root dir?
		if ($this->uri->isGlobalRoot()) {
			foreach ($this->global_root_entries() as $displayname => $entry) {
				if ($name === $displayname) {
					return new Directory(new URI($entry[0], $entry[1]), $this, 'D', null, $this->user, $this->pass);
				}
			}
			$this->exc_notfound($name);
			return false;
		}
		// We have a server, but do we have a share?
		if ($this->uri->isServerRoot()) {
			if (in_array($name, $this->server_root_entries())) {
				$uri = clone $this->uri;
				$uri->addParts($name);
				return new Directory($uri, $this, 'D', null, $this->user, $this->pass);
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
				if ($entry['name'] !== $name) {
					continue;
				}
				$uri = clone $this->uri;
				$uri->addParts($entry['name']);

				if (strpos($entry['flags'], 'D') === false) {
					return new File($uri, $this, $entry['size'], $entry['flags'], $entry['mtime'], $this->user, $this->pass);
				}
				return new Directory($uri, $this, $entry['flags'], $entry['mtime'], $this->user, $this->pass);
			}
		}
		$uri = clone $this->uri;
		$uri->addParts($name);
		$this->exc_notfound($uri->uriFull());
		return false;
	}

	public function createDirectory ($name)
	{
		Log::trace("Directory::createDirectory '%s' '%s'\n", $this->uri->uriFull(), $name);

		// Cannot create directories in the root:
		if ($this->uri->isGlobalRoot() || $this->uri->isServerRoot()) {
			$this->exc_forbidden('Cannot create shares in root');
		}
		switch (SMB::mkdir($this->user, $this->pass, $this->uri, $name)) {
			case SMB::STATUS_OK:
				// Invalidate entries cache:
				$this->cache_destroy();
				return true;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->uri->uriFull());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	public function createFile ($name, $data = NULL)
	{
		$uri = clone $this->uri;
		$uri->addParts($name);

		Log::trace("Directory::createFile '%s'\n", $uri->uriFull());

		if ($this->uri->isGlobalRoot()) {
			$this->exc_forbidden('Cannot create files in global root');
		}
		if ($this->uri->isServerRoot()) {
			$this->exc_forbidden('Cannot create files in server root');
		}
		switch (SMB::put($this->user, $this->pass, $uri, $data, $md5)) {
			case SMB::STATUS_OK:
				// Invalidate entries cache:
				$this->cache_destroy();
				return ($md5 === NULL) ? NULL : "\"$md5\"";

			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->uri->uriFull());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	public function childExists ($name)
	{
		// Are we the global root?
		if ($this->uri->isGlobalRoot()) {
			foreach ($this->global_root_entries() as $displayname => $entry) {
				if ($name === $displayname) {
					return true;
				}
			}
			return false;
		}
		// Are we a server root?
		if ($this->uri->isServerRoot()) {
			return (in_array($name, $this->server_root_entries()));
		}
		if ($this->entries === false) {
			$this->get_entries();
		}
		foreach ($this->entries as $entry) {
			if ($name === $entry['name']) {
				return true;
			}
		}
		return false;
	}

	public function getName ()
	{
		return $this->uri->name();
	}

	public function setName ($name)
	{
		Log::trace("Directory::setName '%s' -> '%s'\n", $this->uri->uriFull(), $name);

		if ($this->uri->isGlobalRoot() || $this->uri->isServerRoot()) {
			$this->exc_forbidden('cannot rename root folders');
		}
		switch (SMB::rename($this->user, $this->pass, $this->uri, $name)) {
			case SMB::STATUS_OK:
				$this->cache_destroy();
				$this->invalidate_parent();
				$this->uri->rename($name);
				return true;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->uri->uriFull());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
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

	public function getQuotaInfo ()
	{
		Log::trace("Directory::getQuotaInfo '%s'\n", $this->uri->uriFull);

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
		if ($this->uri->isGlobalRoot() || !$this->uri->isServerRoot()) {
			return ($quota = false);
		}
		// Get results from disk cache if available and fresh:
		$quota = Cache::get('\SambaDAV\SMB::du', array($this->user, $this->pass, $this->uri), $this->user, $this->pass, 20);
		if (is_array($quota)) {
			return $quota;
		}
		switch ($quota) {
			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->uri->uriFull());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
		}
		return false;
	}

	public function delete ()
	{
		Log::trace("Directory::delete '%s'\n", $this->uri->uriFull());

		if ($this->uri->isGlobalRoot() || $this->uri->isServerRoot()) {
			$this->exc_forbidden('cannot delete root folders');
		}
		// Delete all children:
		foreach ($this->getChildren() as $child) {
			$child->delete();
		}
		// Delete ourselves:
		switch (SMB::rmdir($this->user, $this->pass, $this->uri)) {
			case SMB::STATUS_OK:
				$this->cache_destroy();
				$this->invalidate_parent();
				return true;

			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->uri->uriFull());
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
		Cache::destroy('\SambaDAV\SMB::ls', array($this->user, $this->pass, $this->uri), $this->user);
		$this->entries = false;
	}

	public function setUserhome (URI $uri)
	{
		$this->userhome = $uri;
	}

	private function get_entries ()
	{
		// Get listing from disk cache if available and fresh:
		$this->entries = Cache::get('\SambaDAV\SMB::ls', array($this->user, $this->pass, $this->uri), $this->user, $this->pass, 5);
		if (is_array($this->entries)) {
			return;
		}
		switch ($this->entries) {
			case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->uri->uriFull());
			case SMB::STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case SMB::STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case SMB::STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	private function invalidate_parent ()
	{
		if ($this->parent !== null) {
			$this->parent->cache_destroy();
		}
	}

	private function global_root_entries ()
	{
		// structure:
		// $entries = array('name-of-root-folder' => array('server', 'share-on-that-server'))
		$entries = array();

		foreach (Config::$share_root as $entry)
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
			if (!is_array($shares = Cache::get('\SambaDAV\SMB::getShares', array($this->user, $this->pass, new URI($server)), $this->user, $this->pass, 15))) {
				// TODO: throw an exception?
				// switch ($shares) {
				// 	case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->uri->uriFull());
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
		foreach (Config::$share_extra as $entry) {
			$entries[$entry[0]] = array($entry[0], false);
		}
		// The user's home directory gets a folder with the name of the *user*:
		// User can be false if we allow anonymous logins, in which case ignore:
		if ($this->user !== false && $this->userhome !== null) {
			if ($this->userhome->isServerRoot()) {
				$entries[$this->user] = array($this->userhome->server(), $this->user);
			}
			else {
				$entries[$this->userhome->share()] = array($this->userhome->server(), $this->userhome->share());
			}
		}
		return $entries;
	}

	private function server_root_entries ()
	{
		$entries = array();

		// Shares in the global root belonging to this server
		// also show up in the server's own subdir:
		foreach (Config::$share_root as $entry) {
			$server = (isset($entry[0])) ? $entry[0] : null;
			$share = (isset($entry[1])) ? $entry[1] : null;
			if ($server != $this->uri->server()) {
				continue;
			}
			if ($share === false || $share === null || $share === '') {
				continue;
			}
			$entries[$share] = 1;
		}
		foreach (Config::$share_extra as $entry) {
			$server = (isset($entry[0])) ? $entry[0] : null;
			$share = (isset($entry[1])) ? $entry[1] : null;
			if ($server != $this->uri->server()) {
				continue;
			}
			if ($share !== false && $share !== null && $share !== '') {
				$entries[$share] = 1;
				continue;
			}
			// Only our server name given in $share_extra;
			// this means: autodiscover and use all the shares on this server:
			if (!is_array($shares = Cache::get('\SambaDAV\SMB::getShares', array($this->user, $this->pass, $this->uri), $this->user, $this->pass, 15))) {
				// TODO: throw an exception?
				// switch ($shares) {
				// 	case SMB::STATUS_NOTFOUND: $this->exc_notfound($this->uri->uriFull());
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
		if ($this->user !== false && $this->userhome !== null) {
			if ($this->userhome->server() !== null && $this->userhome->server() == $this->uri->server()) {
				if ($this->userhome->share() === null) {
					$entries[$this->user] = 1;
				}
				else {
					$entries[$this->userhome->share()] = 1;
				}
			}
		}
		return array_keys($entries);
	}

	private function exc_smbclient ()
	{
		$m = 'smbclient error';
		Log::trace("EXCEPTION: '%s': smbclient error\n", $this->uri->uriFull());
		throw new DAV\Exception($m);
	}

	private function exc_forbidden ($msg)
	{
		$m = "Forbidden: $msg";
		Log::trace("EXCEPTION: '%s': %s\n", $this->uri->uriFull(), $m);
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
		$m = sprintf("'%s' not authenticated for '%s'", $this->user, $this->uri->uriFull());
		Log::trace("EXCEPTION: $m\n");
		throw new DAV\Exception\NotAuthenticated($m);
	}

	private function exc_notimplemented ($msg)
	{
		$m = "Not implemented: $msg";
		Log::trace("EXCEPTION: '%s': %s\n", $this->uri->uriFull(), $m);
		throw new DAV\Exception\NotImplemented($m);
	}
}
