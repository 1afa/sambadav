<?php	// $Format:SambaDAV: commit %h @ %cd$

# Copyright (C) 2026  SambaDAV contributors
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

// The look of the web interface that comes from the configuration: the logo
// and title in the page header, and an optional extra stylesheet. Used by
// both the directory listing and the login page, so they stay in step.
//
// Everything that ends up in a page is HTML-escaped here. Nothing is inline
// style or a data: URI, because the pages are served with a Content Security
// Policy of "img-src 'self'; style-src 'self'".

class Theme
{
	const DEFAULT_LOGO  = 'logo.svg';
	const DEFAULT_TITLE = 'SambaDAV';

	private $logo;
	private $title;
	private $css;

	// Monochrome interface icons, drawn with the current text colour.
	// Each entry is the inner markup of a 24x24 stroked SVG.
	private const UI_ICONS = [
		'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
		'upload'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
		'newdir'  => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><path d="M12 11v6"/><path d="M9 14h6"/>',
		'delete'  => '<path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
		'home'    => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
	];

	public function __construct ($config = null)
	{
		// Unset (null) means "use the default"; false or '' means "none":
		$logo  = $config->browserplugin_logo ?? null;
		$title = $config->browserplugin_title ?? null;
		$css   = $config->browserplugin_css ?? null;

		$this->logo  = self::fileName($logo === null ? self::DEFAULT_LOGO : $logo);
		$this->css   = self::fileName($css);
		$this->title = ($title === null)
			? self::DEFAULT_TITLE
			: (is_string($title) ? trim($title) : '');
	}

	// The configured file names live flat in the SambaDAV root, next to
	// style.css; reject anything that could point somewhere else:
	public static function fileName ($value): ?string
	{
		if (!is_string($value) || $value === '') {
			return null;
		}
		return (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value) === 1) ? $value : null;
	}

	public function title (): string
	{
		return $this->title;
	}

	public function logo (): ?string
	{
		return $this->logo;
	}

	public function css (): ?string
	{
		return $this->css;
	}

	public static function escape ($value): string
	{
		return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	// The <link> elements for the stylesheets. $baseuri ends in a slash.
	public function stylesheets (string $baseuri): string
	{
		$base = self::escape($baseuri);
		$html = "<link rel=\"stylesheet\" href=\"{$base}style.css\">\n";

		if ($this->css !== null) {
			$html .= '    <link rel="stylesheet" href="' . $base . self::escape($this->css) . "\">\n";
		}
		return $html;
	}

	// The brand block for the left-hand side of the page header: the logo
	// and/or the title, linking to the root of the site.
	public function brand (string $baseuri): string
	{
		$base = self::escape($baseuri);
		$html = "<a class=\"brand\" href=\"{$base}\">";

		if ($this->logo !== null) {
			// The title next to it already names the site, so the image is
			// decorative then; on its own it has to carry the name:
			$alt = ($this->title === '') ? self::escape(self::DEFAULT_TITLE) : '';
			$html .= '<img class="brand-logo" src="' . $base . self::escape($this->logo) . "\" alt=\"{$alt}\">";
		}
		if ($this->title !== '') {
			$html .= '<span class="brand-title">' . self::escape($this->title) . '</span>';
		}
		return $html . '</a>';
	}

	// An inline interface icon by name; empty string if unknown.
	public static function icon (string $name): string
	{
		if (!isset(self::UI_ICONS[$name])) {
			return '';
		}
		return '<svg class="ui-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" '
		     . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
		     . 'aria-hidden="true" focusable="false">' . self::UI_ICONS[$name] . '</svg>';
	}
}
