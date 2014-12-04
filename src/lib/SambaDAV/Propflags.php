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
# Project page: <https://github.com/bokxing-it/sambadav/>

namespace SambaDAV;

class Propflags
{
	// These flag letters are taken from /source3/client/client.c in the
	// Samba source tarball, function attr_str(). These are the flags we
	// can theoretically expect to see in smbclient's `ls` output.
	// NB: the 'N' flag occurs twice in the smbclient source, but we
	// interpret 'N' to always stand for NORMAL, based on actual listings.
	private $flags = array
		( 'R' => false	// FILE_ATTRIBUTE_READONLY
		, 'H' => false	// FILE_ATTRIBUTE_HIDDEN
		, 'S' => false	// FILE_ATTRIBUTE_SYSTEM
		, 'D' => false	// FILE_ATTRIBUTE_DIRECTORY
		, 'A' => false	// FILE_ATTRIBUTE_ARCHIVE
		, 'N' => false	// FILE_ATTRIBUTE_NORMAL
		, 'T' => false	// FILE_ATTRIBUTE_TEMPORARY
		, 's' => false	// FILE_ATTRIBUTE_SPARSE
		, 'r' => false	// FILE_ATTRIBUTE_REPARSE_POINT
		, 'C' => false	// FILE_ATTRIBUTE_COMPRESSED
		, 'O' => false	// FILE_ATTRIBUTE_OFFLINE
	//	, 'N' => false	// FILE_ATTRIBUTE_NONINDEXED
		, 'E' => false	// FILE_ATTRIBUTE_ENCRYPTED
		) ;

	// These values are from /libcli/smb/smb_constants.h in the Samba
	// source tarball, they are the bitmask values for the Win32 property string:
	private $bitmask = array
		( 'R' => 0x0001
		, 'H' => 0x0002
		, 'S' => 0x0004
		, 'D' => 0x0010
		, 'A' => 0x0020
		, 'N' => 0x0080
		, 'T' => 0x0100
		, 's' => 0x0200
		, 'r' => 0x0400
		, 'C' => 0x0800
		, 'O' => 0x1000
	//	, 'N' => 0x2000	// Skip the NONINDEXED flag: if we see an N, it always stands for NORMAL.
		, 'E' => 0x4000
		) ;

	private $init = false;

	public function
	__construct ($smbflags = null)
	{
		if ($smbflags !== null) $this->fromSmbflags($smbflags);
	}

	public function
	fromWin32 ($msflags)
	{
		if (strlen($msflags) !== 8
		 || sscanf($msflags, '%08x', $flags) !== 1) {
			return $this->init = false;
		}
		foreach (array_keys($this->flags) as $flag) {
			$this->flags[$flag] = (bool)($flags & $this->bitmask[$flag]);
		}
		$this->updateNormal();
		return $this->init = true;
	}

	public function
	toWin32 ()
	{
		if ($this->init === false) return false;

		$msflags = 0;

		// This is the string returned in the proprietary
		// '{urn:schemas-microsoft-com:}Win32FileAttributes' DAV property,
		// used by the DAV client in Windows.
		foreach (array_keys($this->flags) as $flag) {
			if ($this->flags[$flag]) {
				$msflags |= $this->bitmask[$flag];
			}
		}
		return sprintf('%08x', $msflags);
	}

	public function
	diff ($that)
	{
		// Returns an array with zero, one or two strings: the strings
		// needed to go from flags in $this to $that, in `smbclient
		// setmode` format, so '+-shar'. These are the only flags that
		// smbclient supports for toggling, so ignore the rest.

		$ret = [];
		$on = $off = '';

		if (!$this->init || !$that->init) {
			return $ret;
		}
		foreach (['S','H','A','R'] as $flag)
		{
			// Flags that are on in ours and off in theirs must be turned off:
			if ($this->flags[$flag] && !$that->flags[$flag]) {
				$off .= strtolower($flag);
			}
			// Flags that are on in theirs and off in ours must be turned on:
			if ($that->flags[$flag] && !$this->flags[$flag]) {
				$on .= strtolower($flag);
			}
		}
		if (strlen($off)) $ret[] = '-'.$off;
		if (strlen($on))  $ret[] = '+'.$on;

		return $ret;
	}

	public function
	set ($flag, $val)
	{
		$this->flags[$flag] = $val;
		$this->updateNormal();
		$this->init = true;
	}

	public function
	get ($flag)
	{
		return ($this->init) ? $this->flags[$flag] : false;
	}

	private function
	fromSmbflags ($smbflags)
	{
		// The 'smbflags' are the ones found in the output of
		// smbclient's `ls` command. They are case-sensitive.
		foreach (array_keys($this->flags) as $flag) {
			$this->flags[$flag] = (strpos($smbflags, $flag) !== false);
		}
		$this->updateNormal();
		$this->init = true;
	}

	private function
	updateNormal ()
	{
		// The N (NORMAL) flag can only be set if no other flags are set:
		if ($this->flags['N']) {
			foreach ($this->flags as $flag => $value) {
				if ($flag !== 'N' && $value) {
					$this->flags['N'] = false;
					break;
				}
			}
			return;
		}
		// If no flags are set, ensure that N is set:
		foreach ($this->flags as $flag => $value) {
			if ($value) {
				return;
			}
		}
		$this->flags['N'] = true;
	}
}
