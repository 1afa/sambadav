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

class Propflags
{
	// We use the initialism 'shard' to order the flags; prevents typoes.
	public $s = 0;	// System
	public $h = 0;	// Hidden
	public $a = 0;	// Archive
	public $r = 0;	// Readonly
	public $d = 0;	// Directory

	// Taken from Samba, libcli/smb/smb_constants.h:
	//   #define FILE_ATTRIBUTE_READONLY         0x0001L
	//   #define FILE_ATTRIBUTE_HIDDEN           0x0002L
	//   #define FILE_ATTRIBUTE_SYSTEM           0x0004L
	//   #define FILE_ATTRIBUTE_DIRECTORY        0x0010L
	//   #define FILE_ATTRIBUTE_ARCHIVE          0x0020L
	//   #define FILE_ATTRIBUTE_NORMAL           0x0080L
	// If none of the above qualifiers fits, the file is deemed 'NORMAL'.
	private $bitmask = array
		( 's' => 0x0004
		, 'h' => 0x0002
		, 'a' => 0x0020
		, 'r' => 0x0001
		, 'd' => 0x0010
		) ;

	public $init = FALSE;

	public function __construct ($smbflags = FALSE)
	{
		if ($smbflags !== FALSE) $this->from_smbflags($smbflags);
	}

	public function from_win32 ($msflags)
	{
		if (strlen($msflags) !== 8
		 || sscanf($msflags, '%08x', $flags) !== 1) {
			return $this->init = FALSE;
		}
		$this->s = ($flags & $this->bitmask['s']) ? 1 : 0;
		$this->h = ($flags & $this->bitmask['h']) ? 1 : 0;
		$this->a = ($flags & $this->bitmask['a']) ? 1 : 0;
		$this->r = ($flags & $this->bitmask['r']) ? 1 : 0;
		$this->d = ($flags & $this->bitmask['d']) ? 1 : 0;

		return $this->init = TRUE;
	}

	public function to_win32 ()
	{
		if ($this->init === FALSE) return FALSE;

		$msflags = 0;

		// This is the string returned in the proprietary
		// '{urn:schemas-microsoft-com:}Win32FileAttributes' DAV property,
		// used by the DAV client in Windows.
		if ($this->s) $msflags |= $this->bitmask['s'];
		if ($this->h) $msflags |= $this->bitmask['h'];
		if ($this->a) $msflags |= $this->bitmask['a'];
		if ($this->r) $msflags |= $this->bitmask['r'];
		if ($this->d) $msflags |= $this->bitmask['d'];

		// If no flags are set, return the special 'NORMAL' string:
		return ($msflags === 0) ? '00000080' : sprintf('%08x', $msflags);
	}

	public function diff ($that)
	{
		// Returns an array with zero, one or two strings: the strings
		// needed to go from flags in $this to $that, in `smbclient
		// setmode` format, so '+-shar'. Smbclient does not support
		// toggling the 'd' flag, so we skip that one.

		$ret = array();
		$on = $off = '';

		if (!$this->init || !$that->init) return $ret;

		// Flags in $this but not in $that must be turned off:
		if ($this->s && !$that->s) $off .= 's';
		if ($this->h && !$that->h) $off .= 'h';
		if ($this->a && !$that->a) $off .= 'a';
		if ($this->r && !$that->r) $off .= 'r';

		// Flags not in $this but present in $that must be turned on:
		if ($that->s && !$this->s) $on .= 's';
		if ($that->h && !$this->h) $on .= 'h';
		if ($that->a && !$this->a) $on .= 'a';
		if ($that->r && !$this->r) $on .= 'r';

		if (strlen($off)) $ret[] = '-'.$off;
		if (strlen($on))  $ret[] = '+'.$on;

		return $ret;
	}

	private function from_smbflags ($smbflags)
	{
		$this->s = (strpos($smbflags, 'S') === FALSE) ? 0 : 1;
		$this->h = (strpos($smbflags, 'H') === FALSE) ? 0 : 1;
		$this->a = (strpos($smbflags, 'A') === FALSE) ? 0 : 1;
		$this->r = (strpos($smbflags, 'R') === FALSE) ? 0 : 1;
		$this->d = (strpos($smbflags, 'D') === FALSE) ? 0 : 1;

		return $this->init = TRUE;
	}
}
