<?php	// $Format:SambaDAV: commit %h @ %cd$

# Copyright (C) 2013, 2014  Bokxing IT, http://www.bokxing-it.nl
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

ini_set('display_errors', false);

use Sabre\DAV;
use Sabre\HTTP;

include __DIR__ . '/vendor/autoload.php';

// Load config files:
$config = new Config();
$config->load(__DIR__ . '/config');

// if this variable is not unambiguously true, bail out immediately:
if ($config->enabled !== true) {
	header('HTTP/1.1 404 Not Found');
	die();
}

// The base URI is the SambaDAV root dir location on the server.
// Check if the request was rewritten:
$baseuri = (strpos($_SERVER['REQUEST_URI'], $config->server_basedir) === 0) ? $config->server_basedir : '/';

$auth = new Auth($config, $baseuri);

// Run the authentication routines:
if ($auth->exec() === false) {
	return;
}

// Create cache object:
$cache = ($config->cache_use)
	? new Cache\Filesystem($config->cache_dir)
	: new Cache\Null();

// Clean stale cache files every once in a blue moon:
// Time-based throttling to prevent too-frequent rechecking;
// Random-based throttling to prevent contention in the "available" second:
if ((time() % 5) == 0 && rand(0, 9) == 8) {
	$cache->clean();
}
// No server, share and path known in root dir:
$rootDir = new Directory($auth, $config, $cache, new URI(), null, 'D', null);

// Add userhome to root dir:
$rootDir->setUserhome($auth->getUserhome());

// The object tree needs in turn to be passed to the server class:
$server = new DAV\Server($rootDir);
$server->setBaseUri($baseuri);

// Also make sure there is a 'data' directory, writable by the server.
// This directory is used to store information about locks:
$lockBackend = new DAV\Locks\Backend\File('data/locks.dat');
$lockPlugin = new DAV\Locks\Plugin($lockBackend);
$server->addPlugin($lockPlugin);

// Browser plugin, for plain directory listings:
$plugin = new BrowserPlugin();
$server->addPlugin($plugin);

// Content-type plugin:
$plugin = new \Sabre\DAV\Browser\GuessContentType();

// Define some custom types, the default list is really short:
$plugin->extensionMap['gz'] = 'application/x-gzip';
$plugin->extensionMap['bmp'] = 'image/bmp';
$plugin->extensionMap['bz2'] = 'application/x-bzip2';
$plugin->extensionMap['doc'] = 'application/msword';
$plugin->extensionMap['dot'] = 'application/msword';
$plugin->extensionMap['dvi'] = 'application/x-dvi';
$plugin->extensionMap['eps'] = 'application/postscript';
$plugin->extensionMap['htm'] = 'text/html';
$plugin->extensionMap['pdf'] = 'application/pdf';
$plugin->extensionMap['pot'] = 'application/vnd.ms-powerpoint';
$plugin->extensionMap['ppt'] = 'application/vnd.ms-powerpoint';
$plugin->extensionMap['mp3'] = 'audio/mpeg';
$plugin->extensionMap['tgz'] = 'application/x-compressed';
$plugin->extensionMap['tif'] = 'image/tiff';
$plugin->extensionMap['wav'] = 'audio/wav';
$plugin->extensionMap['xla'] = 'application/excel';
$plugin->extensionMap['xlc'] = 'application/excel';
$plugin->extensionMap['xls'] = 'application/excel';
$plugin->extensionMap['xlt'] = 'application/excel';
$plugin->extensionMap['xlw'] = 'application/excel';
$plugin->extensionMap['zip'] = 'application/zip';
$plugin->extensionMap['docx'] = 'application/msword';
$plugin->extensionMap['html'] = 'text/html';
$plugin->extensionMap['mpeg'] = 'video/mpeg';

$server->addPlugin($plugin);

// Compatibility fix for Microsoft Word 2003,
// Otherwise harmless, according to http://sabre.io/dav/clients/msoffice:
\Sabre\DAV\Property\LockDiscovery::$hideLockRoot = true;

// Custom plugin to add the nonstandard DAV:ishidden and DAV:isreadonly flags:
$server->addPlugin(new MSPropertiesPlugin());

// And off we go!
$server->exec();
