<?php	// $Format:SambaDAV: commit %h @ %cd$

# Copyright (C) 2013  Bokxing IT, http://www.bokxing-it.nl
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

namespace SambaDAV\SMBClient;

use \SambaDAV\SMB;

class Parser
{
	private $fd = false;
	private $nline = 0;

	public function
	__construct ($data)
	{
		if ($data instanceof Process) {
			// Use the process's stdout handle for reading:
			$this->fd = $data->getStdoutHandle();
		}
		else {
			// Assume that $data is a file handle:
			$this->fd = $data;
		}
	}

	private function
	getResources ()
	{
		$resources = array();
		while (($line = $this->getLine()) !== false) {
			if (is_array($line)) {
				return $line[0];
			}
			$resources[] = $line;
		}
		return $resources;
	}

	public function
	getShares ()
	{
		// if $resources is not an array, it's an error code:
		if (!is_array($resources = $this->getResources())) {
			return $resources;
		}
		$shares = Array();
		foreach ($resources as $line) {
			if (strpos($line, 'Disk|') !== 0) {
				continue;
			}
			if (($term = strpos($line, '|', 5)) === false || $term === 5) {
				continue;
			}
			$name = substr($line, 5, $term - 5);
			// "Special" shares have a name ending with '$', discard those:
			if (substr($name, -1, 1) === '$') {
				continue;
			}
			$shares[] = $name;
		}
		return $shares;
	}

	public function
	getDiskUsage ()
	{
		// The 'du' command only gives a global total for the entire share;
		// the Unix 'du' can do a tally for a subdir, but this one can't.
		while (($line = $this->getLine()) !== false) {
			if (is_array($line)) {
				return $line[0];
			}
			if (preg_match('/([0-9]+) blocks of size ([0-9]+)\. ([0-9]+) blocks available/', $line, $matches) === 0) {
				continue;
			}
			return Array(
				$matches[2] * ($matches[1] - $matches[3]),	// used space (bytes)
				$matches[2] * $matches[3]			// available space (bytes)
			);
		}
		return false;
	}

	public function
	getStatus ()
	{
		// Parses the smbclient output on stdout, returns SMB::STATUS_OK
		// if everything could be read without encountering errors
		// (as parsed by getLine), else it returns the error code.
		while (($line = $this->getLine()) !== false) {
			if (is_array($line)) {
				return $line[0];
			}
		}
		return SMB::STATUS_OK;
	}

	public function
	getListing ()
	{
		$ret = Array();
		while (($line = $this->getLine()) !== false) {
			if (is_array($line)) {
				return $line[0];
			}
			if (($parsed = $this->parseFileLine($line)) !== false) {
				$ret[] = $parsed;
			}
		}
		return $ret;
	}

	private function
	getLine ()
	{
		// Returns false if no more lines;
		// Returns Array(errorcode) if error found;
		// Returns the line as a string if all is well.

		if (!is_resource($this->fd)) {
			return false;
		}
		while (($line = fgets($this->fd)) !== false)
		{
			// If this is not the first or second line of output,
			// return it verbatim:
			if ($this->nline++ >= 2) {
				return $line;
			}
			// In lines 1 and 2, check if we can match an error code;
			// if not, return the line verbatim:
			if (preg_match('/(NT_STATUS_[A-Z0-9_]*)/', $line, $matches) !== 1) {
				return $line;
			}
			// Translate the error code:
			switch ($matches[1])
			{
				// This is the only status we consider
				// acceptable; continue with next line:
				case 'NT_STATUS_OK':
					continue;

				case 'NT_STATUS_LOGON_FAILURE':
				case 'NT_STATUS_ACCESS_DENIED':	// TODO: this can also mean "not writable"
					return Array(SMB::STATUS_UNAUTHENTICATED);

				case 'NT_STATUS_NO_SUCH_FILE':
				case 'NT_STATUS_BAD_NETWORK_NAME':
				case 'NT_STATUS_OBJECT_PATH_NOT_FOUND':
				case 'NT_STATUS_OBJECT_NAME_NOT_FOUND':
					return Array(SMB::STATUS_NOTFOUND);

				// All other statuses, assume unauthenticated:
				default:
					return Array(SMB::STATUS_UNAUTHENTICATED);
			}
		}
		return false;
	}

	private function
	parseFileLine ($line)
	{
		// Parses a line of smbclient's ls output and returns an array with the following fields:
		//  0 - filename;
		//  1 - flags;
		//  2 - size;
		//  3 - Unix timestamp.

		// The printf format that smbclient uses to print the file data can be
		// found in ./source3/client/client.c, line 549 or thereabouts.
		//
		// d_printf("  %-30s%7.7s %8.0f  %s",
		// 		finfo->name,
		// 		attrib_string(finfo->mode),
		//		(double)finfo->size,
		//		time_to_asc(t));
		//
		// So the string looks like this:
		// - two spaces;
		// - at least 30 characters of space-padded filename;
		// - exactly 7 characters of flags/spaces;
		// - a space;
		// - at least 8 characters of file length;
		// - two spaces;
		// - time string.
		// The time string is its own can of worms, but fairly deterministic-looking.
		// Match from right to left:

		//                  Name Flags          Size                   Mon            Mar            8         13:00:07  2010
		if (preg_match("/^  (.*)([A-Za-z ]{7}) ([0-9]{8,}|[0-9 ]{8})  (.*)$/", rtrim($line), $matches) === 0) {
			return false;
		}
		$output['name'] = rtrim($matches[1]);
		$output['flags'] = trim($matches[2]);
		$output['size'] = (int)$matches[3];

		// Create Unix timestamp from freeform date string:
		$date = date_parse($matches[4]);

		if ($date === false) {
			$output['mtime'] = null;
		}
		else {
			$output['mtime'] = mktime
				( $date['hour'], $date['minute'], $date['second']
				, $date['month'], $date['day'], $date['year']
				) ;
		}
		return $output;
	}
}
