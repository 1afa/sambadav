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
require_once 'function.cache.php';
require_once 'class.propflags.php';

// Dynamic shares config; these are optional includes:
@include_once dirname(dirname(__FILE__)).'/config/share_root.inc.php';
@include_once dirname(dirname(__FILE__)).'/config/share_archives.inc.php';
@include_once dirname(dirname(__FILE__)).'/config/share_extra.inc.php';
@include_once dirname(dirname(__FILE__)).'/config/share_userhomes.inc.php';

// If share variables not sourced, set default (empty) value:
if (!isset($share_root) || !$share_root) $share_root = array();
if (!isset($share_extra) || !$share_extra) $share_extra = array();
if (!isset($share_archives) || !$share_archives) $share_archives = array();
if (!isset($share_userhomes) || !$share_userhomes) $share_userhomes = FALSE;

$share_root = array_merge($share_root, $share_archives);

use Sabre\DAV;

class SMBDirectory extends DAV\FSExt\Directory
{
	private $server = FALSE;	// server that the share is on
	private $share = FALSE;		// name of the share
	private $vpath = FALSE;		// virtual path from root of share
	private $entries = FALSE;
	private $flags = FALSE;		// SMB flags
	private $parent = FALSE;
	private $user = FALSE;		// login credentials
	private $pass = FALSE;

	function __construct ($server, $share, $path, $parent, $smbflags, $user, $pass)
	{
		$this->user = $user;
		$this->pass = $pass;
		$this->server = $server;
		$this->share = $share;
		$this->flags = new Propflags($smbflags);
		$this->vpath = FALSE($path) ? '/' : $path;
		$this->parent = $parent;
	}

	function getChildren ()
	{
		$children = array();

		// If in root folder, show master shares list:
		if (FALSE($this->server)) {
			foreach ($this->global_root_entries() as $entry) {
				$children[] = new SMBDirectory($entry[0], $entry[1], FALSE, $this, 'D', $this->user, $this->pass);
			}
			return $children;
		}
		// If in root folder for given server, fetch all allowed shares for that server:
		if (FALSE($this->share)) {
			foreach ($this->server_root_entries() as $entry) {
				$children[] = new SMBDirectory($this->server, $entry, FALSE, $this, 'D', $this->user, $this->pass);
			}
			return $children;
		}
		// Else, open share, produce listing:
		if (FALSE($this->entries)) {
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

	function getChild ($name)
	{
		log_trace('getChild "'.$this->pretty_name()."$name\"\n");

		// Are we a folder in the root dir?
		if (FALSE($this->server)) {
			foreach ($this->global_root_entries() as $displayname => $entry) {
				if ($name === $displayname) {
					return new SMBDirectory($entry[0], $entry[1], FALSE, $this, 'D', $this->user, $this->pass);
				}
			}
			$this->exc_notfound($name);
			return FALSE;
		}
		// We have a server, but do we have a share?
		if (FALSE($this->share)) {
			if (in_array($name, $this->server_root_entries())) {
				return new SMBDirectory($this->server, $name, FALSE, $this, 'D', $this->user, $this->pass);
			}
			$this->exc_notfound($name);
			return FALSE;
		}
		// We have a server and a share, get entries:
		if (FALSE($this->entries)) {
			$this->get_entries();
		}
		if (!FALSE($this->entries)) {
			foreach ($this->entries as $entry) {
				if ($entry[0] !== $name) {
					continue;
				}
				if (FALSE(strpos($entry[1], 'D'))) {
					return new SMBFile($this->server, $this->share, $this->vpath, $entry, $this, $this->user, $this->pass);
				}
				return new SMBDirectory($this->server, $this->share, $this->vpath.'/'.$entry[0], $this, $entry[1], $this->user, $this->pass);
			}
		}
		$this->exc_notfound($this->pretty_name().$name);
		return FALSE;
	}

	function createDirectory ($name)
	{
		log_trace('createDirectory "'.$this->pretty_name()."$name\"\n");

		// Cannot create directories in the root:
		if (FALSE($this->server) || FALSE($this->share)) {
			$this->exc_forbidden('Cannot create shares in root');
		}
		switch (smb_mkdir($this->user, $this->pass, $this->server, $this->share, $this->vpath, $name)) {
			case STATUS_OK:
				// Invalidate entries cache:
				$this->cache_destroy();
				return TRUE;

			case STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	function createFile ($name, $data = NULL)
	{
		log_trace('createFile "'.$this->pretty_name()."$name\"\n");

		if (FALSE($this->server) || FALSE($this->share)) {
			$this->exc_forbidden('Cannot create files in root');
		}
		switch (smb_put($this->user, $this->pass, $this->server, $this->share, $this->vpath, basename($name), $data, $md5)) {
			case STATUS_OK:
				// Invalidate entries cache:
				$this->cache_destroy();
				return ($md5 === NULL) ? NULL : "\"$md5\"";

			case STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	function childExists ($name)
	{
		// Are we the global root?
		if (FALSE($this->server)) {
			foreach ($this->global_root_entries() as $displayname => $entry) {
				if ($name === $displayname) {
					return TRUE;
				}
			}
			return FALSE;
		}
		// Are we a server root?
		if (FALSE($this->share)) {
			return (in_array($name, $this->server_root_entries()));
		}
		if (FALSE($this->entries)) {
			$this->get_entries();
		}
		foreach ($this->entries as $entry) {
			if ($name === $entry[0]) {
				return TRUE;
			}
		}
		return FALSE;
	}

	function getName ()
	{
		if (FALSE($this->server)) return '/';
		if (FALSE($this->share)) return $this->server;
		if (FALSE($this->vpath) || $this->vpath === '/') return $this->share;
		return basename($this->vpath);
	}

	function setName ($name)
	{
		log_trace('setName "'.$this->pretty_name()."\" -> \"$name\"\n");

		if (FALSE($this->server) || FALSE($this->share) || $this->vpath === '' || $this->vpath === '/') {
			$this->exc_notimplemented('cannot rename root folders');
		}
		switch (smb_rename($this->user, $this->pass, $this->server, $this->share, dirname($this->vpath), basename($this->vpath), $name)) {
			case STATUS_OK:
				$this->cache_destroy();
				$this->invalidate_parent();
				$this->vpath = basename($this->vpath)."/$name";
				return TRUE;

			case STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	function getIsHidden ()
	{
		return $this->flags->get('H');
	}

	function getIsReadonly ()
	{
		return $this->flags->get('R');
	}

	function getWin32Props ()
	{
		return $this->flags->to_win32();
	}

	function getQuotaInfo ()
	{
		log_trace('getQuotaInfo "'.$this->pretty_name()."\"\n");

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
		if (FALSE($this->server) || FALSE($this->share) || $this->vpath !== '/') {
			return ($quota = FALSE);
		}
		// Get results from disk cache if available and fresh:
		$quota = cache_get('smb_du', array($this->user, $this->pass, $this->server, $this->share), $this->user, $this->pass, 20);
		if (is_array($quota)) {
			return $quota;
		}
		switch ($quota) {
			case STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
		}
		return FALSE;
	}

	function delete ()
	{
		log_trace('delete "'.$this->pretty_name()."\"\n");

		if (FALSE($this->server) || FALSE($this->share) || $this->vpath === '' || $this->vpath === '/') {
			$this->exc_notimplemented('cannot delete root folders');
		}
		switch (smb_rmdir($this->user, $this->pass, $this->server, $this->share, dirname($this->vpath), basename($this->vpath))) {
			case STATUS_OK:
				$this->cache_destroy();
				$this->invalidate_parent();
				return TRUE;

			case STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	function updateProperties ($mutations)
	{
		// Stub function, see SMBFile::updateProperties() for more details.
		// By default, Sabre wants to save these properties in a file in the
		// root called .sabredav, but that location is not writable in our
		// setup. Silently ignore for now.

		// In SMBFile::updateProperties(), we use smbclient's `setmode`
		// command to set file flags. Unfortunately, that command only
		// appears to work for files, not directories. So even though
		// we know how to decipher the Win32 propstring we're given, we
		// have no way of setting the flags in the backend.
		return TRUE;
	}

	function cache_destroy()
	{
		cache_destroy('smb_ls', array($this->user, $this->pass, $this->server, $this->share, $this->vpath), $this->user);
		$this->entries = FALSE;
	}

	private function get_entries ()
	{
		// Get listing from disk cache if available and fresh:
		$this->entries = cache_get('smb_ls', array($this->user, $this->pass, $this->server, $this->share, $this->vpath), $this->user, $this->pass, 5);
		if (is_array($this->entries)) {
			return;
		}
		switch ($this->entries) {
			case STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
			case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
			case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
			case STATUS_INVALID_NAME: $this->exc_forbidden('invalid pathname or filename');
		}
	}

	private function invalidate_parent ()
	{
		if (!FALSE($this->parent)) {
			$this->parent->cache_destroy();
		}
	}

	private function pretty_name ()
	{
		// Return a string with the "pretty name" of this resource: no double slashes, etc:
		// Guaranteed to end with a slash.
		if (FALSE($this->server)) return '/';
		$str = "//{$this->server}";
		if (FALSE($this->share)) return $str;
		if (substr($str, -1, 1) != '/') $str .= '/'; if (FALSE($this->share)) return $str; $str .= (($this->share[0] == '/') ? substr($this->share, 1) : $this->share);
		if (substr($str, -1, 1) != '/') $str .= '/'; if (FALSE($this->vpath)) return $str; $str .= (($this->vpath[0] == '/') ? substr($this->vpath, 1) : $this->vpath);
		if (substr($str, -1, 1) != '/') $str .= '/';
		return $str;
	}

	private function global_root_entries ()
	{
		global $share_root;
                global $share_extra;
		global $share_userhomes;

		// structure:
		// $entries = array('name-of-root-folder' => array('server', 'share-on-that-server'))
		$entries = array();

		foreach ($share_root as $entry)
		{
			$server = (isset($entry[0])) ? $entry[0] : FALSE;
			$share  = (isset($entry[1])) ? $entry[1] : FALSE;

			if (FALSE($server)) {
				continue;
			}
			if ($share !== FALSE && $share !== NULL && $share !== '') {
				$entries[$share] = array($server, $share);
				continue;
			}
			// Just the server name given; autodiscover all shares on this server:
			if (!is_array($shares = cache_get('smb_get_shares', array($server, $this->user, $this->pass), $this->user, $this->pass, 15))) {
				// TODO: throw an exception?
				// switch ($shares) {
				// 	case STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
				// 	case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
				// 	case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
				// }
				continue;
			}
			foreach ($shares as $share) {
				$entries[$share] = array($server, $share);
			}
		}
		// Servers from $shares_extra get a folder with the name of the *server*:
		foreach ($share_extra as $entry) {
			$entries[$entry[0]] = array($entry[0], FALSE);
		}
		// The user's home directory gets a folder with the name of the *user*:
		// User can be FALSE if we allow anonymous logins, in which case ignore:
		if (!FALSE($this->user) && $share_userhomes) {
			$entries[$this->user] = array($share_userhomes, $this->user);
		}
		return $entries;
	}

	private function server_root_entries ()
	{
		global $share_root;
		global $share_extra;
		global $share_userhomes;

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
			if ($share !== FALSE && $share !== NULL && $share !== '') {
				$entries[$share] = 1;
				continue;
			}
			// Only our server name given in $share_extra;
			// this means: autodiscover and use all the shares on this server:
			if (!is_array($shares = cache_get('smb_get_shares', array($this->server, $this->user, $this->pass), $this->user, $this->pass, 15))) {
				// TODO: throw an exception?
				// switch ($shares) {
				// 	case STATUS_NOTFOUND: $this->exc_notfound($this->pretty_name());
				// 	case STATUS_SMBCLIENT_ERROR: $this->exc_smbclient();
				// 	case STATUS_UNAUTHENTICATED: $this->exc_unauthenticated();
				// }
				continue;
			}
			foreach ($shares as $share) {
				$entries[$share] = 1;
			}
		}
		// User's home share is on this server?
		if (!FALSE($this->user) && $share_userhomes && $share_userhomes == $this->server) {
			$entries[$this->user] = 1;
		}
		return array_keys($entries);
	}

	private function exc_smbclient ()
	{
		$m = 'smbclient error';
		log_trace('EXCEPTION: "'.$this->pretty_name()."\": $m\n");
		throw new DAV\Exception($m);
	}

	private function exc_forbidden ($msg)
	{
		$m = "Forbidden: $msg";
		log_trace('EXCEPTION: "'.$this->pretty_name()."\": $m\n");
		throw new DAV\Exception\Forbidden($m);
	}

	private function exc_notfound ($name)
	{
		$m = "Not found: \"$name\"";
		log_trace("EXCEPTION: $m\n");
		throw new DAV\Exception\NotFound($m);
	}

	private function exc_unauthenticated ()
	{
		$m = '"'.$this->user.'" not authenticated for "'.$this->pretty_name()."\"";
		log_trace("EXCEPTION: $m\n");
		throw new DAV\Exception\NotAuthenticated($m);
	}

	private function exc_notimplemented ($msg)
	{
		$m = "Not implemented: $msg";
		log_trace('EXCEPTION: "'.$this->pretty_name()."\": $m\n");
		throw new DAV\Exception\Forbidden($m);
	}
}
