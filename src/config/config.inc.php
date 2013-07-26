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

// Base directory on webserver (the root URL):
define('SERVER_BASEDIR', '/webfolders/');

// Full path to the smbclient utility:
define('SMBCLIENT_PATH', '/usr/bin/smbclient');

// Allow anonymous logins/browsing (no username/pass):
define('ANONYMOUS_ALLOW', FALSE);

// Allow *only* anonymous logins: this disables the password prompt; implies ANONYMOUS_ALLOW:
define('ANONYMOUS_ONLY', FALSE);

// Don't procure eTags for files larger than this size (bytes):
// NB: calculating the eTag is very resource-intensive, because the file must
// be streamed through smbclient and md5summed. The (limited) utility of eTags
// doesn't really justify the significant processing overhead.
define('ETAG_SIZE_LIMIT', -1);

// Set to TRUE to use disk cache in /dev/shm/webfolders, FALSE to disable:
define('CACHE_USE', TRUE);

// Dir to use for cache files; preferably keep this in /dev/shm for speed;
// the lowest-level directory is created if not exists:
define('CACHE_DIR', '/dev/shm/webfolders');

// Use LDAP authentication on top of smbclient authentication?
define('LDAP_AUTH', FALSE);

// Array of LDAP group(s) that the user must be a member of, FALSE for bind-only check:
$ldap_groups = FALSE;
