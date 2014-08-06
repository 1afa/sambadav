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

class LoginForm
{
	private $baseuri = null;

	public function
	__construct ($baseuri)
	{
		$this->baseuri = $baseuri;
	}

	public function
	getBody ()
	{
		$template = __DIR__ . '/loginform.html';

		// Open the login form template in current dir:
		if (!is_readable($template)) {
			return null;
		}
		if (($contents = file_get_contents($template)) === false) {
			return null;
		}
		// Substitute {BASEURI}:
		return str_replace('{BASEURI}', $this->baseuri, $contents);
	}
}
