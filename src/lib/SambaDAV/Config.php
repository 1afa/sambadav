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
		// Source all php files in the config dir:
		if ($dir = opendir($cfgpath)) {
			while (($entry = readdir($dir)) !== false) {
				if (substr($entry, -4) === '.php') {
					$keys = include "{$cfgpath}/{$entry}";
					if (is_array($keys)) {
						$this->keys = array_merge($this->keys, $keys);
					}
				}
			}
			closedir($dir);
		}
		// Make sure that share_root and share_extra exist as arrays,
		// even if they're empty:
		if (!is_array($this->share_root)) {
			$this->share_root = array();
		}
		if (!is_array($this->share_extra)) {
			$this->share_extra = array();
		}
		// Merge "archives" keys into the list of root shares:
		if (is_array($this->share_archives)) {
			$this->share_root = array_merge($this->share_root, $this->share_archives);
		}
		// Master on/off switch:
		$this->enabled = ($this->enable_webfolders === true);
	}
}
