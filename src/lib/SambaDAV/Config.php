<?php	// $Format:SambaDAV: commit %h @ %cd$

# Copyright (C) 2014  Bokxing IT, http://www.bokxing-it.nl
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

class Config
{
	private $keys = array();

	public function
	__get ($name)
	{
		return (isset($this->keys[$name]))
			? $this->keys[$name]
			: null;
	}

	public function
	__set ($name, $value)
	{
		$this->keys[$name] = $value;
	}

	public function
	__isset ($name)
	{
		return isset($this->keys[$name]);
	}

	public function
	load ($cfgpath)
	{
		// These variables can be set by the config files:
		$share_root = [];
		$share_extra = [];
		$share_archives = [];
		$share_userhomes = null;
		$share_userhome_ldap = null;
		$ldap_groups = null;

		// Source all php files in the config dir:
		if ($dir = opendir($cfgpath)) {
			while (($entry = readdir($dir)) !== false) {
				if (substr($entry, -4) === '.php') {
					include "{$cfgpath}/{$entry}";
				}
			}
			closedir($dir);
		}
		$this->share_root = array_merge($share_root, $share_archives);
		$this->share_extra = $share_extra;
		$this->share_userhomes = $share_userhomes;
		$this->share_userhome_ldap = $share_userhome_ldap;
		$this->ldap_groups = $ldap_groups;
		$this->enabled = (isset($enable_webfolders) && $enable_webfolders === true) ? true : false;

		// Some settings have historically been set with define();
		// incorporate those too:
		if (defined('SERVER_BASEDIR')) {
			$this->server_basedir = SERVER_BASEDIR;
		}
		if (defined('SMBCLIENT_PATH')) {
			$this->smbclient_path = SMBCLIENT_PATH;
		}
		if (defined('SMBCLIENT_EXTRA_OPTS')) {
			$this->smbclient_extra_opts = SMBCLIENT_EXTRA_OPTS;
		}
		if (defined('ANONYMOUS_ALLOW')) {
			$this->anonymous_allow = ANONYMOUS_ALLOW;
		}
		if (defined('ANONYMOUS_ONLY')) {
			$this->anonymous_only = ANONYMOUS_ONLY;
		}
		if (defined('ETAG_SIZE_LIMIT')) {
			$this->etag_size_limit = ETAG_SIZE_LIMIT;
		}
		if (defined('CACHE_USE')) {
			$this->cache_use = CACHE_USE;
		}
		if (defined('CACHE_DIR')) {
			$this->cache_dir = CACHE_DIR;
		}
		if (defined('LDAP_AUTH')) {
			$this->ldap_auth = LDAP_AUTH;
		}
	}
}
