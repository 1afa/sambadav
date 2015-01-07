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
# Project page: <https://github.com/1afa/sambadav/>

return array(

	// This variable MUST be set to true for SambaDAV to work; server.php will exit
	// with a 404 error if this variable has any other value than true. Rationale
	// is to have a single master on/off switch for the SambaDAV service:
	'enable_webfolders' => true,

	// Base directory on webserver (the root URL):
	'server_basedir' => '/webfolders/',

	// Full path to the smbclient utility:
	'smbclient_path' => '/usr/bin/smbclient',

	// Extra options to smbclient, pasted verbatim on commandline if not false;
	// see manpage for smbclient; example: '--port 6789 -O TCP_NODELAY'
	'smbclient_extra_opts' => false,

	// Allow anonymous logins/browsing (no username/pass):
	'anonymous_allow' => false,

	// Allow *only* anonymous logins: this disables the password prompt; implies 'anonymous_allow':
	'anonymous_only' => false,

	// Don't procure eTags for files larger than this size (bytes):
	// NB: calculating the eTag is very resource-intensive, because the file must
	// be streamed through smbclient and md5summed. The (limited) utility of eTags
	// doesn't really justify the significant processing overhead.
	'etag_size_limit' => -1,

	// Set to true to use disk cache in /dev/shm/webfolders, false to disable:
	'cache_use' => true,

	// Dir to use for cache files; preferably keep this in /dev/shm for speed;
	// the lowest-level directory is created if not exists:
	'cache_dir' => '/dev/shm/webfolders',

	// Use LDAP authentication on top of smbclient authentication?
	'ldap_auth' => false,

	// Hostname and basedn to use; string, or null to look them up in /etc/ldap.conf:
	'ldap_host' => null,
	'ldap_basedn' => null,

	// LDAP bind method, 'bind' for an AD style bind, 'fastbind' for a user bind:
	'ldap_method' => 'fastbind',

	// Auth DN and password for an AD style bind:
	'ldap_authdn' => null,
	'ldap_authpass' => null,

	// Array of LDAP group(s) that the user must be a member of, false for bind-only check:
	'ldap_groups' => false,

	// If you set this variable to the name of the server that contains the
	// userhomes, SambaDAV will add the user's home directory to the topmost list
	// of directories. For example, if your server is called 'JUPITER', setting
	// $share_userhomes to the string 'JUPITER' and logging in with user 'john'
	// will auto-add the share 'john' on 'JUPITER' to the list of shares.
	'share_userhomes' => false,

	// If this variable is set to the name of a LDAP property, then that property
	// will be queried for the location of the user's home. Example: if every user
	// has an LDAP property called 'sambaHomePath', then by setting the variable
	// below to 'sambaHomePath', SambaDAV will read the value of that property and
	// use it as the home directory. Currently, the value in LDAP must look like:
	//   \\servername            (will be expanded to \\servername\username)
	//   \\servername\sharename
	'share_userhome_ldap' => false,

	// The username that a user logs in with can be dissected as follows:
	// workgroup\username@domain
	//  %w = workgroup
	//  %u = username
	//  %d = domain
	//
	// The %w, %u and %d placeholders can be used in pattern strings.
	// The username is always available by definition, unless anonymous
	// login is allowed. The other two placeholders can be undefined if the
	// user didn't enter them. If you specify a pattern that can't be
	// filled from the user's input, the application will abort the
	// request.

	// Pattern for the userhomes. If defined, this will override 'share_userhomes'.
	// Example: '//SERVER/%u':
	'userhome_pattern' => null,

	// Pattern to create the username with that's used for LDAP authentication:
	// Example: '%u-%w'
	'ldap_username_pattern' => '%u',

	// Pattern to create the username with that's passed to Samba for authentication:
	// Example: '%w\\%u'
	'samba_username_pattern' => '%u',

	// Pattern to create the domain with that's passed to Samba for authentication:
	'samba_domain_pattern' => null,

	// Log level: can be 'none', 'error', 'warn', 'info', 'debug' or 'trace',
	// in order of verboseness from least to most:
	'log_level' => 'warn',

);
