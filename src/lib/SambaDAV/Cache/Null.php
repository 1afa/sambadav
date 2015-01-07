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
# Project page: <https://github.com/1afa/sambadav/>

namespace SambaDAV\Cache;

class Null extends \SambaDAV\Cache
{
	// This class does nothing but throw away the cache data;
	// it's for when the admin has turned off caching.

	public function
	write ($key, $data, $timeout)
	{
		return true;
	}

	public function
	read ($key, &$data, $timeout)
	{
		return false;
	}

	public function
	delete ($key)
	{
		return true;
	}

	public function
	clean ()
	{
		return true;
	}

	public function
	get ($callable, $args = array(), $auth, $uri, $timeout)
	{
		return call_user_func_array($callable, $args);
	}

	public function
	remove ($callable, $auth, $uri)
	{
		return true;
	}
}
