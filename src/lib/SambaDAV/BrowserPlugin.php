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

namespace SambaDAV;

use Sabre\DAV;
use Sabre\HTTP;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class BrowserPlugin extends DAV\Browser\Plugin
{
	private $config;
	private $theme;

	public function
	__construct ($config)
	{
		parent::__construct();
		$this->config = $config;
		$this->theme = new Theme($config);
	}

	public function 
	initialize(DAV\Server $server) 
	{
		parent::initialize($server);
		$this->server = $server;
		if ($this->enablePost) $this->server->on('method:POST', [$this,'httpPOSTExtra']);
	}


    	/**
    	 * Handles POST requests for tree operations not handled in the SabreDAV parent clas
    	 *
    	 * @param RequestInterface $request
    	 * @param ResponseInterface $response
    	 * @return bool
    	 */
	public function 
	httpPOSTExtra(RequestInterface $request, ResponseInterface $response) 
	{

		$contentType = (string)$request->getHeader('Content-Type');
		[$contentType] = explode(';', $contentType);
		if ($contentType !== 'application/x-www-form-urlencoded' &&
		$contentType !== 'multipart/form-data') {
			return;
		}

		$postVars = $request->getPostData();
		
		if (!isset($postVars['sabreActionExtra']))
			return;

		$uri = $request->getPath();
		
		switch($postVars['sabreActionExtra']) {
			case 'del':
				if (isset($postVars['path'])) {
					// Using basename() because we won't allow slashes
					[, $Name] = \Sabre\Uri\split(trim($postVars['path']));
					if(!empty($Name) && $this->config->browserplugin_enable_delete === true) {
						$this->server->tree->delete($uri . '/' . $Name);
					}
				}
				break;
		}

		$response->setHeader('Location', $request->getUrl());
		$response->setStatus(302);
		return false;
	}

	// Name of the directory entry, as a string safe for HTML:
	private function
	esc ($value)
	{
		return Theme::escape($value);
	}

	// The breadcrumb trail: "Shares / share / dir / subdir", every part
	// but the last one a link:
	private function
	breadcrumbs ($path, $baseuri)
	{
		$home = Theme::icon('home') . '<span>Shares</span>';

		if ($path === '') {
			return '';
		}
		$html = '<li><a href="' . $this->esc($baseuri) . "\">{$home}</a></li>";

		$segments = explode('/', $path);
		$last = count($segments) - 1;
		$acc = '';

		foreach ($segments as $i => $segment) {
			$acc .= ($acc === '' ? '' : '/') . $segment;

			if ($i === $last) {
				$html .= '<li aria-current="page"><span>' . $this->esc($segment) . '</span></li>';
				continue;
			}
			$href = HTTP\encodePath($baseuri . $acc);
			$html .= '<li><a href="' . $this->esc($href) . '">' . $this->esc($segment) . '</a></li>';
		}
		return $html;
	}

	// One table row for a directory entry:
	private function
	row ($props, $canDelete)
	{
		$isDir = isset($props['{DAV:}resourcetype'])
			&& in_array('{DAV:}collection', $props['{DAV:}resourcetype']->getValue());

		$name = $props['displayPath'];
		$icon = FileType::icon($name, $isDir);
		$href = $this->esc($props['fullPath']);

		$size = ($isDir)
			? '<span class="muted">&mdash;</span>'
			: $this->esc(FileType::humanSize($props['{DAV:}getcontentlength'] ?? null));

		$date = '';
		$dateTitle = '';
		if (isset($props['{DAV:}getlastmodified'])) {
			// Show the time in the server's own timezone, not in UTC:
			$time = clone $props['{DAV:}getlastmodified']->getTime();
			$time->setTimezone(new \DateTimeZone(date_default_timezone_get()));
			$date = $time->format('Y-m-d H:i');
			$dateTitle = $time->format('Y-m-d H:i:s T');
		}
		$class = $isDir ? 'dir' : 'file';

		$html  = "        <tr class=\"{$class}\">\n";
		$html .= "          <td class=\"col-name\"><a href=\"{$href}\"><span class=\"icon icon-{$icon}\" aria-hidden=\"true\"></span><span class=\"name\">" . $this->esc($name) . "</span></a>"
		       . (($date !== '') ? "<div class=\"meta\">{$date}</div>" : '') . "</td>\n";
		$html .= "          <td class=\"col-size\">{$size}</td>\n";
		$html .= '          <td class="col-date" title="' . $this->esc($dateTitle) . "\">{$date}</td>\n";

		if ($canDelete) {
			// The name ends up in a JavaScript string inside an HTML attribute:
			$confirm = $this->esc(json_encode(
				'Are you sure you want to delete ' . $name . '?',
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
			));
			$decoded = $this->esc(HTTP\decodePath($props['fullPath']));
			$label = $this->esc('Delete ' . $name);

			$html .= "          <td class=\"col-act\">\n";
			$html .= "            <form method=\"post\" enctype=\"multipart/form-data\" onsubmit=\"return confirm({$confirm});\">\n";
			$html .= "              <input name=\"sabreActionExtra\" value=\"del\" type=\"hidden\">\n";
			$html .= "              <input name=\"path\" value=\"{$decoded}\" type=\"hidden\">\n";
			$html .= "              <button class=\"icon-btn danger\" type=\"submit\" title=\"{$label}\" aria-label=\"{$label}\">" . Theme::icon('delete') . "</button>\n";
			$html .= "            </form>\n";
			$html .= "          </td>\n";
		}
		return $html . "        </tr>\n";
	}

	public function
	generateDirectoryIndex ($path)
	{
		$path = (string)$path;
		$baseuri = $this->server->getBaseUri();
		$node = $this->server->tree->getNodeForPath($path);

		$canDelete = ($this->config->browserplugin_enable_delete === true);
		$isRoot = ($path === '');

		[, $here] = \Sabre\Uri\split($path);
		$heading = $isRoot ? 'Shares' : $here;
		$smbpath = (!$isRoot && $node instanceof Directory) ? $node->uri->uriFull() : '';

		$siteTitle = ($this->theme->title() !== '') ? $this->theme->title() : Theme::DEFAULT_TITLE;
		$pageTitle = $this->esc($heading . ' – ' . $siteTitle);

		$styles = $this->theme->stylesheets($baseuri);
		$brand = $this->theme->brand($baseuri);
		$crumbs = $this->breadcrumbs($path, $baseuri);
		$crumbs = ($crumbs !== '')
			? "<nav class=\"crumbs\" aria-label=\"Location\"><ol>{$crumbs}</ol></nav>"
			: '';
		$h1 = $this->esc($heading);

		$logout = '';
		if ($this->config->anonymous_only === false) {
			$logout = '<a class="logout" href="?logout">' . Theme::icon('logout') . '<span>Sign out</span></a>';
		}
		$smbline = ($smbpath !== '')
			? '<p class="smbpath">' . $this->esc($smbpath) . '</p>'
			: '';

		// Actions panel (new folder, upload), if any plugin has something to show:
		$output = '';
		if ($this->enablePost) {
			$this->server->emit('onHTMLActionsPanel', [$node, &$output, $path]);
		}
		$actions = ($output !== '')
			? "<section class=\"actions\" aria-label=\"Actions\">\n{$output}\n    </section>"
			: '';

		// The rows of the listing:
		$rows = '';
		$columns = 3 + ($canDelete ? 1 : 0);

		if ($path !== '') {
			[$parentUri] = \Sabre\Uri\split($path);
			$parentHref = $this->esc(HTTP\encodePath($baseuri . $parentUri));

			$rows .= "        <tr class=\"up\">\n";
			$rows .= "          <td class=\"col-name\"><a href=\"{$parentHref}\"><span class=\"icon icon-up\" aria-hidden=\"true\"></span><span class=\"name\">Parent folder</span></a></td>\n";
			$rows .= "          <td class=\"col-size\"></td>\n";
			$rows .= "          <td class=\"col-date\"></td>\n";
			$rows .= ($canDelete) ? "          <td class=\"col-act\"></td>\n" : '';
			$rows .= "        </tr>\n";
		}
		$entries = [];

		if ($node instanceof DAV\ICollection) {
			$entries = $this->server->getPropertiesForChildren($path, [
				'{DAV:}displayname',
				'{DAV:}resourcetype',
				'{DAV:}getcontenttype',
				'{DAV:}getcontentlength',
				'{DAV:}getlastmodified',
			]);
			foreach ($entries as $subPath => $props) {
				$entries[$subPath]['fullPath'] = HTTP\encodePath($baseuri . $subPath);
				[, $entries[$subPath]['displayPath']] = \Sabre\Uri\split($subPath);
			}
			// Directories first, then by name:
			uasort($entries, [$this, 'compareNodes']);
		}
		foreach ($entries as $props) {
			$rows .= $this->row($props, $canDelete);
		}
		if ($entries === []) {
			$rows .= "        <tr class=\"empty\"><td colspan=\"{$columns}\">This folder is empty.</td></tr>\n";
		}
		$deleteHead = ($canDelete) ? '<th class="col-act"><span class="sr-only">Delete</span></th>' : '';

		$version = (DAV\Server::$exposeVersion) ? ' ' . DAV\Version::VERSION : '';
		$footer = $this->esc('Generated by SabreDAV' . $version);

		$this->server->httpResponse->setHeader('Content-Security-Policy', "img-src 'self'; style-src 'self';");

		return <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{$pageTitle}</title>
    {$styles}    <link rel="shortcut icon" href="{$this->esc($baseuri)}favicon.ico" type="image/vnd.microsoft.icon">
  </head>
  <body class="browser">
    <div class="wrapper">
      <header class="topbar">
        <div class="container">
          {$brand}
          {$logout}
        </div>
      </header>
      <main class="container content">
        {$crumbs}
        <h1>{$h1}</h1>
        {$smbline}
        {$actions}
        <div class="listing">
          <table>
            <thead>
              <tr>
                <th class="col-name">Name</th>
                <th class="col-size">Size</th>
                <th class="col-date">Modified</th>
                {$deleteHead}
              </tr>
            </thead>
            <tbody>
{$rows}            </tbody>
          </table>
        </div>
      </main>
      <footer class="footer"><div class="container">{$footer}</div></footer>
    </div>
  </body>
</html>
HTML;
	}

	public function
	htmlActionsPanel (DAV\INode $node, &$output, $path)
	{
		if (!$node instanceof DAV\ICollection) {
			return;
		}
		// Shares cannot be created or filled from the web interface, so do
		// not offer the forms in the list of shares or of a server's shares:
		if ($node instanceof Directory && ($node->uri->isGlobalRoot() || $node->uri->isServerRoot())) {
			return;
		}
		$newdir = Theme::icon('newdir');
		$upload = Theme::icon('upload');

		$output .= <<<HTML
      <form class="card" method="post" action="">
        <h2>{$newdir}<span>New folder</span></h2>
        <input type="hidden" name="sabreAction" value="mkcol">
        <div class="field-row">
          <input type="text" name="name" placeholder="Folder name" aria-label="Folder name" required>
          <button class="btn primary" type="submit">Create</button>
        </div>
      </form>
      <form class="card" method="post" action="" enctype="multipart/form-data">
        <h2>{$upload}<span>Upload file</span></h2>
        <input type="hidden" name="sabreAction" value="put">
        <div class="field-row">
          <input type="file" name="file" aria-label="File" required>
          <input type="text" name="name" placeholder="Save as (optional)" aria-label="Name to save the file as (optional)">
          <button class="btn primary" type="submit">Upload</button>
        </div>
      </form>
HTML;
	}

}
