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
	public static $share_root = null;
	public static $share_extra = null;
	public static $share_userhomes = null;
	public static $share_userhome_ldap = null;
	public static $enabled = false;
	public static $ldap_groups = null;

	public static function
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
		self::$share_root = array_merge($share_root, $share_archives);
		self::$share_extra = $share_extra;
		self::$share_userhomes = $share_userhomes;
		self::$share_userhome_ldap = $share_userhome_ldap;
		self::$ldap_groups = $ldap_groups;

		if (isset($enable_webfolders) && $enable_webfolders === true) {
			self::$enabled = true;
		}
	}
}
