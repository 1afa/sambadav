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
	public $share_root = null;
	public $share_extra = null;
	public $share_userhomes = null;
	public $share_userhome_ldap = null;
	public $enabled = false;
	public $ldap_groups = null;

	public function
	load ($cfgpath)
	{
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

		if (isset($enable_webfolders) && $enable_webfolders === true) {
			$this->enabled = true;
		}
	}
}
