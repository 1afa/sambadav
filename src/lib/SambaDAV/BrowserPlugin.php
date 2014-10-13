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

use Sabre\DAV;

class BrowserPlugin extends DAV\Browser\Plugin
{
	private $anonymous_only;

	public function
	__construct ($anonymous_only = false)
	{
		parent::__construct();
		$this->anonymous_only = $anonymous_only;
	}

	public function
	generateDirectoryIndex ($path)
	{
		$version = (DAV\Server::$exposeVersion)
			? ' ' . DAV\Version::VERSION ."-". DAV\Version::STABILITY
			: '';

		$parent = $this->server->tree->getNodeForPath($path);

		$html =
<<<HTML
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Index for {$this->escapeHTML($path)}/ - SambaDAV</title>
    <link rel="stylesheet" href="{$this->server->getBaseUri()}style.css"/>
    <link rel="shortcut icon" href="{$this->server->getBaseUri()}favicon.ico" type="image/vnd.microsoft.icon"/>
  </head>
  <body>

HTML;

		if ($this->anonymous_only === false) {
			$html .= "
    <p id=\"logout\"><a href=\"?logout\">switch user (logout)</a></p>";
		}

		$html .=
<<<HTML
    <h1>{$this->escapeHTML($parent->uri->uriFull())}</h1>
    <table id="actions">
      <tbody>

HTML;

		$output = '';

		if ($this->enablePost) {
			$this->server->broadcastEvent('onHTMLActionsPanel', array($parent, &$output));
		}
		$html .= $output;

		$html .=
<<<HTML
      </tbody>
    </table>
    <table>
      <colgroup>
        <col style="width:15px"/>
        <col/>
        <col/>
        <col/>
        <col/>
      </colgroup>
      <thead>
        <tr>
          <th></th>
          <th>Name</th>
          <th>Type</th>
          <th>Size</th>
          <th>Last modified</th>
        </tr>
      </thead>
      <tbody>

HTML;
		$files = $this->server->getPropertiesForPath($path, array(
			'{DAV:}displayname',
			'{DAV:}resourcetype',
			'{DAV:}getcontenttype',
			'{DAV:}getcontentlength',
			'{DAV:}getlastmodified',
		), 1);

		if ($path) {
			list($parentUri) = DAV\URLUtil::splitPath($path);
			$fullPath = DAV\URLUtil::encodePath($this->server->getBaseUri() . $parentUri);

			$icon = "<a href=\"$fullPath\"><img src=\"{$this->server->getBaseUri()}dir.png\" alt=\"Parent\"/></a>";

		$html .=
<<<HTML
        <tr class="dir">
          <td>$icon</td>
          <td><a href="$fullPath">..</a></td>
          <td>[parent]</td>
          <td></td>
          <td></td>
        </tr>

HTML;
		}

		// Sort files by href (filename):
		uasort($files, 'self::compare_filenames');

		foreach ($files as $file)
		{
			// This is the current directory, we can skip it
			if (rtrim($file['href'],'/') == $path) {
				continue;
			}
			list(, $name) = DAV\URLUtil::splitPath($file['href']);
			$type = null;

			if (isset($file[200]['{DAV:}resourcetype'])) {
				$type = $file[200]['{DAV:}resourcetype']->getValue();

				// resourcetype can have multiple values
				if (!is_array($type)) $type = array($type);

				foreach ($type as $k => $v) {
					// Some name mapping is preferred
					if ($v === '{DAV:}collection') {
						$type = 'Directory';
						break;
					}
				}
			}

			// If no resourcetype was found, we attempt to use
			// the contenttype property
			if (!$type && isset($file[200]['{DAV:}getcontenttype'])) {
				$type = $file[200]['{DAV:}getcontenttype'];
			}
			if (!$type) $type = 'Unknown';

			$size = isset($file[200]['{DAV:}getcontentlength'])?(int)$file[200]['{DAV:}getcontentlength']:'';
			$lastmodified = isset($file[200]['{DAV:}getlastmodified'])?$file[200]['{DAV:}getlastmodified']->getTime()->format(\DateTime::RFC2822):'';

			$fullPath = DAV\URLUtil::encodePath('/' . trim($this->server->getBaseUri() . ($path?$path . '/':'') . $name,'/'));

			$displayName = isset($file[200]['{DAV:}displayname'])?$file[200]['{DAV:}displayname']:$name;

			$displayName = $this->escapeHTML($displayName);
			$type = $this->escapeHTML($type);

			$icon = '';

			$node = $this->server->tree->getNodeForPath(($path?$path.'/':'') . $name);
			foreach (array_reverse($this->iconMap) as $class => $iconName) {
				if ($node instanceof $class) {
					$icon = "<a href=\"$fullPath\"><img src=\"{$this->server->getBaseUri()}".(($iconName == 'icons/collection') ? 'dir.png' : 'file.png') . '" alt=""/></a>';
					break;
				}
			}
			$trclass = ($type == 'Directory') ? 'class="dir"' : 'class="file"';

			$html .=
<<<HTML
        <tr $trclass>
          <td>$icon</td>
          <td><a href="{$fullPath}">{$displayName}</a></td>
          <td>{$type}</td>
          <td>{$size}</td>
          <td>{$lastmodified}</td>
        </tr>

HTML;
		}
		$html .= <<<HTML
      </tbody>
    </table>
    <img src="{$this->server->getBaseUri()}logo-sambadav.png" style="float:right;margin:5px"/><address>Generated by SabreDAV $version</address>
  </body>
</html>
HTML;
		return $html;
	}

	public function
	htmlActionsPanel (DAV\INode $node, &$output)
	{
		if (!$node instanceof DAV\ICollection) {
			return;
		}

		$output .= <<<HTML
<tr><form method="post"><input name="sabreAction" value="mkcol" type="hidden">
  <td><h3>New folder</h3></td>
  <td><label for="name">Name:</label></td>
  <td colspan="3"><input name="name" type="text"></td>
  <td><input value="create" type="submit"></td>
  </form>
</tr>
<tr><form method="post" enctype="multipart/form-data"><input name="sabreAction" value="put" type="hidden">
  <td><h3>Upload file</h3></td>
  <td><label for="file">File:</label></td>
  <td><input name="file" type="file"></td>
  <td><label for="name">Name (optional):</label></td>
  <td><input name="name" type="text"></td>
  <td><input value="upload" type="submit"></td>
</form></tr>
HTML;

	}

	private function
	compare_filenames ($a, $b)
	{
		// Helper function for uasort; sort file list: directories
		// before files, else alphabetically:
		$isdir_a = $isdir_b = false;

		if (isset($a[200]['{DAV:}resourcetype'])) {
			$isdir_a = $a[200]['{DAV:}resourcetype']->getValue();
			$isdir_a = (isset($isdir_a[0]) && $isdir_a[0] == '{DAV:}collection');
		}
		if (isset($b[200]['{DAV:}resourcetype'])) {
			$isdir_b = $b[200]['{DAV:}resourcetype']->getValue();
			$isdir_b = (isset($isdir_b[0]) && $isdir_b[0] == '{DAV:}collection');
		}
		// Unequal types? Directories always win:
		if ($isdir_a && !$isdir_b) return -1;
		if ($isdir_b && !$isdir_a) return 1;

		// Equal types? Sort alphabetically:
		if (!isset($a['href']) || !isset($b['href']) || $a['href'] == $b['href']) {
			return 0;
		}
		return strcasecmp($a['href'], $b['href']);
	}
}
