<?php

namespace SambaDAV;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
	private function
	config (array $keys)
	{
		$config = new Config();
		foreach ($keys as $k => $v) {
			$config->$k = $v;
		}
		return $config;
	}

	public function
	testDefaults ()
	{
		// An old config file that predates these options gets the stock look:
		$theme = new Theme(new Config());
		$this->assertSame('logo.svg', $theme->logo());
		$this->assertSame('SambaDAV', $theme->title());
		$this->assertNull($theme->css());

		$theme = new Theme(null);
		$this->assertSame('logo.svg', $theme->logo());
	}

	public function
	testCustomValues ()
	{
		$theme = new Theme($this->config(['browserplugin_logo' => 'acme.png', 'browserplugin_title' => ' Acme Files ', 'browserplugin_css' => 'custom.css']));
		$this->assertSame('acme.png', $theme->logo());
		$this->assertSame('Acme Files', $theme->title());
		$this->assertSame('custom.css', $theme->css());
	}

	public function
	testDisabled ()
	{
		$theme = new Theme($this->config(['browserplugin_logo' => false, 'browserplugin_title' => '']));
		$this->assertNull($theme->logo());
		$this->assertSame('', $theme->title());
		$this->assertSame('<a class="brand" href="/"></a>', $theme->brand('/'));
	}

	public static function
	badFileNames ()
	{
		return
		[ [ '../etc/passwd' ], [ '/abs/logo.png' ], [ 'sub/logo.png' ], [ 'http://x.test/l.png' ]
		, [ 'logo.png"onerror="x' ], [ '.hidden' ], [ '' ], [ 'a b.png' ], [ 12 ], [ true ]
		] ;
	}

	#[DataProvider('badFileNames')]
	public function
	testFileNameRejectsPaths ($name)
	{
		$this->assertNull(Theme::fileName($name));
	}

	public function
	testFileNameAccepts ()
	{
		$this->assertSame('logo-2.v3.svg', Theme::fileName('logo-2.v3.svg'));
	}

	public function
	testBrandWithLogoAndTitle ()
	{
		$theme = new Theme($this->config(['browserplugin_logo' => 'acme.png', 'browserplugin_title' => 'Acme']));
		$html = $theme->brand('/webfolders/');

		$this->assertStringContainsString('href="/webfolders/"', $html);
		$this->assertStringContainsString('src="/webfolders/acme.png"', $html);
		$this->assertStringContainsString('alt=""', $html);	// the title names the site
		$this->assertStringContainsString('<span class="brand-title">Acme</span>', $html);
	}

	public function
	testBrandLogoOnlyHasAlt ()
	{
		$theme = new Theme($this->config(['browserplugin_logo' => 'acme.png', 'browserplugin_title' => '']));
		$html = $theme->brand('/');

		$this->assertStringContainsString('alt="SambaDAV"', $html);
		$this->assertStringNotContainsString('brand-title', $html);
	}

	public function
	testTitleIsEscaped ()
	{
		$theme = new Theme($this->config(['browserplugin_title' => '<script>alert(1)</script> & "co"']));
		$html = $theme->brand('/');

		$this->assertStringNotContainsString('<script>', $html);
		$this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;co&quot;', $html);
	}

	public function
	testBaseUriIsEscaped ()
	{
		$html = (new Theme(new Config()))->brand('/a"b/');
		$this->assertStringNotContainsString('a"b', $html);
	}

	public function
	testStylesheets ()
	{
		$html = (new Theme(new Config()))->stylesheets('/dav/');
		$this->assertStringContainsString('<link rel="stylesheet" href="/dav/style.css">', $html);
		$this->assertStringNotContainsString('custom', $html);

		$html = (new Theme($this->config(['browserplugin_css' => 'custom.css'])))->stylesheets('/dav/');
		$this->assertStringContainsString('href="/dav/style.css"', $html);
		$this->assertStringContainsString('href="/dav/custom.css"', $html);
		// The custom sheet must come after the stock one, or it cannot override it:
		$this->assertTrue(strpos($html, 'custom.css') > strpos($html, 'style.css'));
	}

	public function
	testIcons ()
	{
		$this->assertStringContainsString('<svg', Theme::icon('logout'));
		$this->assertStringContainsString('aria-hidden="true"', Theme::icon('delete'));
		$this->assertSame('', Theme::icon('no-such-icon'));
	}

	public function
	testNoInlineStyleOrDataUris ()
	{
		// The pages are served with "img-src 'self'; style-src 'self'", which
		// blocks style attributes and data: URIs; do not produce either:
		$theme = new Theme($this->config(['browserplugin_logo' => 'acme.png']));
		$html = $theme->brand('/') . $theme->stylesheets('/');
		foreach (['logout', 'upload', 'newdir', 'delete', 'home'] as $name) {
			$html .= Theme::icon($name);
		}
		$this->assertStringNotContainsString('style=', $html);
		$this->assertStringNotContainsString('data:', $html);
	}
}
