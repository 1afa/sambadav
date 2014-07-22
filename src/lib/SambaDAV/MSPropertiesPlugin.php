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

// A plugin to mixin the DAV:ishidden and DAV:isreadonly properties to nodes
// that provide the appropriate methods.

namespace SambaDAV;

use Sabre\DAV;

class MSPropertiesPlugin extends DAV\ServerPlugin
{
	public function initialize (DAV\Server $server)
	{
		$server->subscribeEvent('afterGetProperties', array($this, 'afterGetProperties'));
	}

	function afterGetProperties ($uri, &$properties, \Sabre\DAV\INode $node)
	{
		$isHidden   = (method_exists($node, 'getIsHidden'))   ? $node->getIsHidden()   : false;
		$isReadonly = (method_exists($node, 'getIsReadonly')) ? $node->getIsReadonly() : false;
		$winProps   = (method_exists($node, 'getWin32Props')) ? $node->getWin32Props() : false;

		if ($isHidden   !== false) $this->addProp($properties, '{DAV:}ishidden',   $isHidden);
		if ($isReadonly !== false) $this->addProp($properties, '{DAV:}isreadonly', $isReadonly);
		if ($winProps   !== false) $this->addProp($properties, '{urn:schemas-microsoft-com:}Win32FileAttributes', $winProps);

		return true;
	}

	function addProp (&$properties, $key, $val)
	{
		if (isset($properties[404][$key])) {
			unset($properties[404][$key]);
		}
		$properties[200][$key] = $val;
	}
}
