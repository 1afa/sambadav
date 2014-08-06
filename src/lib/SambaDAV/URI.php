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

class URI
{
	// An URI consists of multiple parts, the first being the server,
	// the second the share, and the rest forming the path on the share:
	private $parts = [];

	public function
	__construct ()
	{
		// Allow a variable number of string arguments:
		foreach (func_get_args() as $arg) {
			if (!is_string($arg)) {
				continue;
			}
			$this->addParts($arg);
		}
	}

	public function
	__toString ()
	{
		return $this->uriFull();
	}

	public function
	uriFull ()
	{
		// Get full path from all parts:
		return '//'.implode('/', $this->parts);
	}

	public function
	uriServer ()
	{
		if (!isset($this->parts[0])) {
			return '//';
		}
		return "//{$this->parts[0]}";
	}

	public function
	uriServerShare ()
	{
		// Get just server and share:
		if (!isset($this->parts[0])) {
			return '//';
		}
		if (!isset($this->parts[1])) {
			return "//{$this->parts[0]}";
		}
		return "//{$this->parts[0]}/{$this->parts[1]}";
	}

	public function
	server ()
	{
		return (isset($this->parts[0]))
			? $this->parts[0]
			: null;
	}

	public function
	share ()
	{
		return (isset($this->parts[1]))
			? $this->parts[1]
			: null;
	}

	public function
	path ()
	{
		// Get just the path part:
		return (isset($this->parts[2]))
			? '/'.implode('/', array_slice($this->parts, 2))
			: '/';
	}

	public function
	parentDir ()
	{
		// Get the dir name leading up to the last path part:
		$parts = array_slice($this->parts, 2, count($this->parts) - 3);
		return '/'.implode('/', $parts);
	}

	public function
	name ()
	{
		// Get the last path part:
		if (($count = count($this->parts)) === 0) {
			return '';
		}
		return $this->parts[$count - 1];
	}

	public function
	rename ($name)
	{
		// Remove the last filename part from the stack:
		array_pop($this->parts);
		$this->addParts($name);
	}

	public function
	addParts ($parts)
	{
		if (!is_string($parts)) {
			return;
		}
		// Translate backslashes to slashes,
		// handle strings of the form '\\server\share':
		$parts = strtr($parts, '\\', '/');

		// Add new parts:
		foreach (explode('/', $parts) as $part) {
			if ($part !== '') {
				$this->parts[] = $part;
			}
		}
	}

	public function
	isGlobalRoot ()
	{
		// Global root: not even on a specific server:
		return (!isset($this->parts[0]));
	}

	public function
	isServerRoot ()
	{
		// Server root: on a server, but not on a share:
		return (isset($this->parts[0]) && !isset($this->parts[1]));
	}

	public function
	isWinSafe ()
	{
		// Windows filenames cannot contain " * : < > ? \ / |
		// or characters 1..31. Also exclude \0 as a matter of course.
		// We need a sanitizer, because smbclient can be tricked into
		// running local shell commands by feeding it a command 
		// starting with '!'. Ensure pathnames do not contain newlines 
		// and other special chars (ironically, '!' itself is allowed):
		$bad = sprintf(
			'"*:<>?\/|%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c',
			 0,  1,  2,  3,  4,  5,  6,  7,  8,  9,
			10, 11, 12, 13, 14, 15, 16, 17, 18, 19,
			20, 21, 22, 23, 24, 25, 26, 27, 28, 29,
			30, 31
		);
		foreach ($this->parts as $part) {
			if (strpbrk($part, $bad)) {
				return false;
			}
		}
		return true;
	}
}
