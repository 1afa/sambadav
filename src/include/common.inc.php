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

define('STATUS_OK',			0);
define('STATUS_NOTFOUND',		1);
define('STATUS_UNAUTHENTICATED',	2);
define('STATUS_INVALID_NAME',		3);
define('STATUS_SMBCLIENT_ERROR',	4);

function FALSE ($expr)
{
	return ($expr === FALSE);
}

function win32_propstring ($isReadonly, $isHidden, $isSystem, $isDir, $isArchive)
{
	// Taken from Samba, libcli/smb/smb_constants.h:
	//   #define FILE_ATTRIBUTE_READONLY         0x0001L
	//   #define FILE_ATTRIBUTE_HIDDEN           0x0002L
	//   #define FILE_ATTRIBUTE_SYSTEM           0x0004L
	//   #define FILE_ATTRIBUTE_DIRECTORY        0x0010L
	//   #define FILE_ATTRIBUTE_ARCHIVE          0x0020L
	//   #define FILE_ATTRIBUTE_NORMAL           0x0080L
	// If none of the above qualifiers fits, the file is deemed 'NORMAL'.

	// This is the string returned in the proprietary
	// '{urn:schemas-microsoft-com:}Win32FileAttributes' DAV property,
	// used by the DAV client in Windows.

	if (!$isReadonly && !$isHidden && !$isSystem && !$isDir && !$isArchive) return '00000080';

	$isReadonly = ($isReadonly) ? 0x0001 : 0;
	$isHidden   = ($isHidden)   ? 0x0002 : 0;
	$isSystem   = ($isSystem)   ? 0x0004 : 0;
	$isDir      = ($isDir)      ? 0x0010 : 0;
	$isArchive  = ($isArchive)  ? 0x0020 : 0;

	return sprintf("%08x", $isReadonly | $isHidden | $isSystem | $isDir | $isArchive);
}
