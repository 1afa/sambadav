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

// Presentation helpers for the web interface: which icon belongs to a file
// name, and how to print a size. Kept free of SabreDAV so it is easy to test.

final class FileType
{
	// File extension => icon name. An icon name 'x' corresponds to the file
	// 'icon-x.svg' in the SambaDAV root and to the CSS class 'icon-x':
	private const ICONS = [
		'pdf'     => 'pdf',

		'doc'     => 'doc',   'docx' => 'doc',   'dot'  => 'doc',   'dotx' => 'doc',
		'odt'     => 'doc',   'rtf'  => 'doc',   'pages' => 'doc',

		'xls'     => 'sheet', 'xlsx' => 'sheet', 'xlsm' => 'sheet', 'xlt'  => 'sheet',
		'ods'     => 'sheet', 'csv'  => 'sheet', 'numbers' => 'sheet',

		'ppt'     => 'slides', 'pptx' => 'slides', 'pps' => 'slides', 'pot' => 'slides',
		'odp'     => 'slides', 'key'  => 'slides',

		'txt'     => 'text',  'md'   => 'text',  'log'  => 'text',  'ini'  => 'text',
		'conf'    => 'text',  'rst'  => 'text',  'nfo'  => 'text',

		'png'     => 'image', 'jpg'  => 'image', 'jpeg' => 'image', 'gif'  => 'image',
		'bmp'     => 'image', 'tif'  => 'image', 'tiff' => 'image', 'webp' => 'image',
		'svg'     => 'image', 'ico'  => 'image', 'heic' => 'image', 'eps'  => 'image',

		'mp3'     => 'audio', 'wav'  => 'audio', 'flac' => 'audio', 'ogg'  => 'audio',
		'oga'     => 'audio', 'm4a'  => 'audio', 'aac'  => 'audio', 'wma'  => 'audio',
		'opus'    => 'audio',

		'mp4'     => 'video', 'mkv'  => 'video', 'avi'  => 'video', 'mov'  => 'video',
		'wmv'     => 'video', 'webm' => 'video', 'mpg'  => 'video', 'mpeg' => 'video',
		'm4v'     => 'video', 'flv'  => 'video',

		'zip'     => 'archive', 'gz' => 'archive', 'tgz' => 'archive', 'bz2' => 'archive',
		'xz'      => 'archive', '7z' => 'archive', 'rar' => 'archive', 'tar' => 'archive',
		'zst'     => 'archive', 'iso' => 'archive',

		'php'     => 'code',  'js'   => 'code',  'mjs'  => 'code',  'ts'   => 'code',
		'py'      => 'code',  'sh'   => 'code',  'bash' => 'code',  'pl'   => 'code',
		'rb'      => 'code',  'java' => 'code',  'c'    => 'code',  'h'    => 'code',
		'cpp'     => 'code',  'hpp'  => 'code',  'cs'   => 'code',  'go'   => 'code',
		'rs'      => 'code',  'html' => 'code',  'htm'  => 'code',  'css'  => 'code',
		'json'    => 'code',  'xml'  => 'code',  'yml'  => 'code',  'yaml' => 'code',
		'sql'     => 'code',
	];

	// Name of the icon for a directory entry: 'folder', or one of the file
	// types above, or the generic 'file' for anything we don't recognise.
	public static function icon (string $name, bool $isDir = false): string
	{
		if ($isDir) {
			return 'folder';
		}
		// The extension is whatever follows the last dot, but a leading dot
		// alone (".bashrc") does not make an extension:
		$dot = strrpos($name, '.');

		if ($dot === false || $dot === 0 || $dot === strlen($name) - 1) {
			return 'file';
		}
		$ext = strtolower(substr($name, $dot + 1));

		return self::ICONS[$ext] ?? 'file';
	}

	// Human-readable size: "512 B", "1.5 KB", "12 MB". Not a number gives ''.
	public static function humanSize ($bytes): string
	{
		if ($bytes === null || $bytes === '' || !is_numeric($bytes) || $bytes < 0) {
			return '';
		}
		$size = (float)$bytes;

		if ($size < 1024) {
			return sprintf('%d B', $size);
		}
		foreach (['KB', 'MB', 'GB', 'TB'] as $unit) {
			$size /= 1024;

			if ($size < 1024 || $unit === 'TB') {
				// One decimal for small numbers, none once it stops adding information:
				return ($size < 10)
					? sprintf('%.1f %s', $size, $unit)
					: sprintf('%.0f %s', $size, $unit);
			}
		}
		return '';
	}
}
