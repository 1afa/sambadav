<?php

namespace SambaDAV;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileTypeTest extends TestCase
{
	public static function
	iconProvider ()
	{
		return
		[ [ 'report.pdf',        false, 'pdf'     ]
		, [ 'REPORT.PDF',        false, 'pdf'     ]	// case-insensitive
		, [ 'letter.docx',       false, 'doc'     ]
		, [ 'budget.xlsx',       false, 'sheet'   ]
		, [ 'deck.pptx',         false, 'slides'  ]
		, [ 'notes.txt',         false, 'text'    ]
		, [ 'photo.jpeg',        false, 'image'   ]
		, [ 'song.mp3',          false, 'audio'   ]
		, [ 'clip.mp4',          false, 'video'   ]
		, [ 'backup.tar.gz',     false, 'archive' ]	// last extension counts
		, [ 'index.php',         false, 'code'    ]
		, [ 'README',            false, 'file'    ]	// no extension
		, [ '.bashrc',           false, 'file'    ]	// a leading dot is not an extension
		, [ 'trailingdot.',      false, 'file'    ]
		, [ 'strange.zzzz',      false, 'file'    ]	// unknown extension
		, [ 'a.b.c.pdf',         false, 'pdf'     ]
		, [ 'folder',            true,  'folder'  ]
		, [ 'looks-like.pdf',    true,  'folder'  ]	// directories ignore the name
		, [ '',                  false, 'file'    ]
		] ;
	}

	#[DataProvider('iconProvider')]
	public function
	testIcon ($name, $isDir, $expect)
	{
		$this->assertSame($expect, FileType::icon($name, $isDir));
	}

	public function
	testEveryIconHasAFileAndAStyle ()
	{
		// Each icon name that FileType can return must have its icon-<name>.svg
		// in the web root, and a CSS rule that points at it:
		$root = dirname(__DIR__, 2) . '/src';
		$css = file_get_contents($root . '/style.css');

		foreach (['folder', 'up', 'file', 'text', 'pdf', 'doc', 'sheet', 'slides', 'image', 'audio', 'video', 'archive', 'code'] as $icon) {
			$this->assertTrue(is_file("$root/icon-$icon.svg"), "missing icon-$icon.svg");
			$this->assertTrue(strpos($css, ".icon-$icon") !== false, "no CSS for icon-$icon");
		}
	}

	public static function
	sizeProvider ()
	{
		return
		[ [ 0,             '0 B'     ]
		, [ 5,             '5 B'     ]
		, [ 1023,          '1023 B'  ]
		, [ 1024,          '1.0 KB'  ]
		, [ 1536,          '1.5 KB'  ]
		, [ 10240,         '10 KB'   ]
		, [ 184320,        '180 KB'  ]
		, [ 1048576,       '1.0 MB'  ]
		, [ '2457600',     '2.3 MB'  ]	// numeric strings, as in the DAV properties
		, [ 734003200,     '700 MB'  ]
		, [ 1073741824,    '1.0 GB'  ]
		, [ 5 * 1099511627776, '5.0 TB' ]
		, [ 2048 * 1099511627776, '2048 TB' ]	// no larger unit to fall back on
		, [ null,          ''        ]
		, [ '',            ''        ]
		, [ 'abc',         ''        ]
		, [ -1,            ''        ]
		] ;
	}

	#[DataProvider('sizeProvider')]
	public function
	testHumanSize ($bytes, $expect)
	{
		$this->assertSame($expect, FileType::humanSize($bytes));
	}
}
