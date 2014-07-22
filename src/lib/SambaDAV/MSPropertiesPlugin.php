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

// A plugin to mixin the DAV:ishidden and DAV:isreadonly properties to nodes
// that provide the appropriate methods.

namespace SambaDAV;

use Sabre\DAV;

class MSPropertiesPlugin extends DAV\ServerPlugin
{
	protected $server;

	public function
	initialize (DAV\Server $server)
	{
		$this->server = $server;
		$this->server->on('propFind', [ $this, 'propFind' ]);
		$this->server->on('propPatch', [ $this, 'propPatch' ]);
	}

	public function
	propFind (DAV\PropFind $propFind, DAV\INode $node)
	{
		// Add extra Windows properties to the node:
		if (method_exists($node, 'getIsHidden')) {
			$propFind->set('{DAV:}ishidden', ($node->getIsHidden() ? '1' : '0'));
		}
		if (method_exists($node, 'getIsReadonly')) {
			$propFind->set('{DAV:}isreadonly', ($node->getIsReadonly() ? '1' : '0'));
		}
		if (method_exists($node, 'getWin32Props')) {
			$propFind->set('{urn:schemas-microsoft-com:}Win32FileAttributes', $node->getWin32Props());
		}
	}

	public function
	propPatch ($path, DAV\PropPatch $propPatch)
	{
		$node = $this->server->tree->getNodeForPath($path);

		// The File object is the only thing we can change properties on:
		if (!($node instanceof \SambaDAV\File)) {
			return;
		}
		// These properties are silently ignored for now;
		// smbclient has no 'touch' command; for documentation purposes:
		//  {urn:schemas-microsoft-com:}Win32CreationTime
		//  {urn:schemas-microsoft-com:}Win32LastAccessTime
		//  {urn:schemas-microsoft-com:}Win32LastModifiedTime

		$handled = [ '{DAV:}ishidden'
		           , '{DAV:}isreadonly'
		           , '{urn:schemas-microsoft-com:}Win32FileAttributes'
		           ] ;

		$propPatch->handle($handled, [ $node, 'updateProperties' ]);
	}
}
