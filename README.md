![SambaDAV](https://raw.github.com/bokxing-it/sambadav/master/img/png/logo.png)

# SambaDAV

SambaDAV is an SMB-to-WebDAV bridge, written in PHP and running as a web
application on Linux servers. It acts as the glue between Windows Samba/CIFS
filesharing and WebDAV internet file access, to provide:

- Secure, worldwide access to your shares and files through any web browser.
  Upload and download files over nothing more than standard HTTPS without the
  need to setup a VPN.

- Access to your remote shares directly on your computer as network drives.
  Open and edit your files and shares as if they were on a local network drive,
  from any location and with any device that supports WebDAV.

SambaDAV provides the advantages of the cloud (access to your files from
anywhere), while leaving control over your data and your security entirely with
you. You can host your Windows network shares as your own "private cloud" over
standard and secure HTTPS.

Release tarballs can be downloaded [here](https://github.com/bokxing-it/sambadav/releases).

After installing and configuring SambaDAV, a WebDAV-aware service listens for
requests at an entrypoint URL such as `https://www.example.com/webfolders`. If
you visit this URL in a browser, you get a password box where you enter your
username and password. This will open a web page with a directory and file
listing. You can walk through the file tree, create directories, and download
or upload files.

As useful as this is, the real power of SambaDAV is in its WebDAV support. If
you enter the entrypoint URL into Windows' "Map Network Drive" dialog, or Mac
OS X's "connect to server" dialog, you can mount your shares as a fully
functional network disk with its own drive letter. Fully functional here means
that you can edit, copy, move, delete and rename files and directories just
like they were on a local network share. All this over simple HTTPS and without
setting up a VPN.

[![Build Status](https://travis-ci.org/bokxing-it/sambadav.svg)](https://travis-ci.org/bokxing-it/sambadav)

## How does it work?

In the background, SambaDAV executes SMB commands on the user's behalf through
`smbclient`, a commandline utility distributed with
[Samba](http://www.samba.org), to issue requests to SMB/CIFS (i.e. Windows)
fileservers. Currently, only Samba 3.x is supported; the version of `smbclient`
bundled with Samba 4.x was completely rewritten and supports a different range
of options.

When the user requests a file, SambaDAV will spawn an `smbclient` child process
to retrieve the file and pass it through to the user as a bytestream. User
authentication and all the actual Samba calls are handled by `smbclient` just
as if the request was done on the command line. The resulting output (or
bytestream) is parsed by SambaDAV to a WebDAV response (such as "here's the
file", "file not found", or "could not authenticate") and sent back to the
user. The WebDAV protocol is handled by
[SabreDAV](http://sabre.io/dav), a WebDAV server library written in PHP.

SambaDAV is glue code, which:

- translates WebDAV requests to `smbclient` calls;

- runs `smbclient` on behalf of the user;

- captures and parsing the output, and:

- sends the output back as a WebDAV response.

This may seem like a bit of a hack, but it's actually remarkably reliable,
resilient and performant for what it is. Some features:

- File uploading/downloading is all fully streaming through the use of PHP
  streams and Unix pipes. No temporary files are created on the server. In
  practice you can read and write gigabyte-sized files at many tens of
  megabytes per second.

- Supports UTF-8 filenames.

- Supports read-only and hidden attribute flags.

- Supports Windows XP (but you'll need a valid SSL certificate and some
  registry hacks, see elsewhere).

- Robust decoding of the `smbclient` output (as buttoned-down and mistrusting
  as possible, using hints derived from the `smbclient` source code).

- Caches lookups and responses in memory (using a filesystem-based cache in
  /dev/shm, shared memory) and properly invalidates the cache when a resource
  changes.

- Supports multiple servers, multiple shares, and dynamic userhomes based on
  the username or the value of an LDAP property.

- Supports anonymous (guest) logins if you enable it.

- Passes the username and password to `smbclient` through an unnamed pipe (an
  anonymous file descriptor), which makes this sensitive data fairly hard to
  intercept (and invisible in the process table).

- Supports group-based LDAP authentication as an cheap extra authentication
  check before making expensive calls to `smbclient`.


## History and provenance

SambaDAV was written by [Bokxing IT BV](http://www.bokxing-it.nl), Delft, the
Netherlands, to supplement a line of small business servers. We are releasing
it because we believe that this software is useful and fills a need, and to
give something back to the open-source community. There are other projects that
offer something similar, such as
[Davenport](http://davenport.sourceforge.net) and smbwebclient.php, but we
wrote SambaDAV because those projects show their age and did not fit our needs.


## License

SambaDAV is licensed under the terms of the GNU Affero GPL version 3. Please
refer to the
[LICENSE](https://github.com/bokxing-it/sambadav/blob/master/LICENSE) file in
the project root. Please contact the authors if you have any licensing
questions or requests.


## Installation

SambaDAV only works on Linux servers, since among other things, it uses the
Unix concept of unnamed pipes to communicate with and control `smbclient`.

We assume you have installed and configured your Samba server, and can do
commandline lookups with `smbclient`:

```sh
# This should print a list of shares on your Samba server:
smbclient -N -L //yourserver
```

Download a SambaDAV release tarball
[here](https://github.com/bokxing-it/sambadav/releases). Unpack it in some
directory, we'll assume `/tmp` for convenience:

```sh
cd /tmp
tar xvf /path/to/sambadav-version.tar.gz
```

Copy the application source to a directory on on the web server. We'll assume
that the application directory is `/var/www/htdocs/webfolders`:

```sh
cp -ar /tmp/sambadav-version/src /var/www/htdocs/webfolders
```

Install [SabreDAV](http://sabre.io/dav) using [Composer](http://getcomposer.org):

```sh
cd /var/www/htdocs/webfolders
composer --optimize-autoloader install
```

The following directories should be made writable for the webserver:

- `log`: tracelogs are written here;
- `data`: the place where SabreDAV keeps lockfiles.

If your webserver runs as the user `apache`:

```sh
chown apache:root /var/www/htdocs/webfolders/{log,data}
chmod 0750 /var/www/htdocs/webfolders/{log,data}
```

At this point all the files are in the right place, all that's left is
to configure the webserver and SambaDAV.


## Webserver configuration

We assume that you're running your web server on the standard port 443 (because
you're using SSL!) and want to serve SambaDAV from a subdirectory. In the
browser, SambaDAV will be available via `https://example.com/webfolders`. For
native WebDAV clients we'll do one better and allow them to connect straight to
`http://example.com/`, by redirecting them in two steps to the proper location.
This is more than just fancy: it's the only way the internal Windows XP WebDAV
client will connect to a subfolder over SSL at all.

The webserver configuration is fairly involved because we want to support as
wide a range of clients as possible. Support for Windows XP's built-in WebDAV
client in particular is tricky to set up. The Windows XP client always connects
to the root of the domain at port 80. In order to get it to use SSL at port 443
and use `/webfolders`, we must take the following steps:

1. Detect that a Windows XP client is trying to connect to port 80 at the
   root of the domain by sniffing the user agent string;

2. In the Apache config for the server at port 80, add a rewrite rule to send
   the Windows XP client to the same location but at port 443, using a 302
   Redirect response which the Windows XP client will honor while upgrading
   to SSL;

3. In the Apache config for the server at port 443, add a second set of rewrite
   rules that match only the Windows XP client and transparently proxy a
   request of the form `/<request>`, to `/webfolders/<request>` internally
   without telling the client.

After these three steps, connecting with the Windows XP WebDAV client to
`http://www.example.com/` will properly redirect to
`https://www.example.com/webfolders/`.

Because this trick is convenient for everyone who uses a native WebDAV client,
we've expanded the list of eligible user agents from just the Windows XP
internal one to a full range. This goes in the virtual host section for your
plain HTTP server on port 80:

```apache
# Workaround for the builtin WinXP WebDAV client: it can only connect to the
# server root (/), over plain HTTP, port 80. Fortunately, it *does* honor 302
# redirect responses, and *even* upgrades to SSL if asked! So we use browser
# string matching to redirect the WinXP client to the root of our SSL server,
# from where it will be redirected once more to the actual location.
# This enables driveletter mapping in Windows XP.
# It's also useful for other clients, so we also catch the most popular ones:
RewriteCond %{HTTP_USER_AGENT} "^Microsoft-WebDAV-MiniRedir" [OR]
RewriteCond %{HTTP_USER_AGENT} "^(DAV|Dav|dav)" [OR]
RewriteCond %{HTTP_USER_AGENT} "^WebDAV"        [OR]
RewriteCond %{HTTP_USER_AGENT} "^Microsoft Data Access Internet Publishing Provider" [OR]
RewriteCond %{HTTP_USER_AGENT} "^Microsoft Office" [OR]
RewriteCond %{HTTP_USER_AGENT} "^WebDrive"      [OR]
RewriteCond %{HTTP_USER_AGENT} "^iWorkHTTPKit"  [OR]
RewriteCond %{HTTP_USER_AGENT} "^gnome-vfs"     [OR]
RewriteCond %{HTTP_USER_AGENT} "^Dreamweaver-WebDAV-SCM1" [OR]
RewriteCond %{HTTP_USER_AGENT} "^BitKinex/"     [OR]
RewriteCond %{HTTP_USER_AGENT} "^cadaver/"      [OR]
RewriteCond %{HTTP_USER_AGENT} "^neon/"         [OR]
RewriteCond %{HTTP_USER_AGENT} "^Cyberduck/"    [OR]
RewriteCond %{HTTP_USER_AGENT} "^gvfs/"         [OR]
RewriteCond %{HTTP_USER_AGENT} "^Transmit"
RewriteRule ^/(.*) https://%{HTTP_HOST}:443/$1  [R]

# Other clients are upgraded to SSL:
RewriteRule ^/webfolders(.*) https://%{HTTP_HOST}/webfolders$1 [R]
```

Since, for the sake of this manual, we are using the `/webfolders`
subdirectory, we also want to transparently redirect WebDAV clients who arrive
at the root of the server to `/webfolders`. Clients who reach this point always
do so over SSL (since otherwise they would have been caught by the snippet
above), so the following goes in the virtual host configuration for the SSL
server on port 443:

```apache
# Special rule for the WinXP driveletter mounter;
# it will connect to the server root, and must be transparently
# proxied to the actual location:
# Also useful for other DAV clients:
# Exception: when client requests //server/webfolders, he should not be
# rewritten to /webfolders/webfolders, but end up in /webfolders.
RewriteCond %{HTTP_USER_AGENT} "^Microsoft-WebDAV-MiniRedir" [OR]
RewriteCond %{HTTP_USER_AGENT} "^(DAV|Dav|dav)" [OR]
RewriteCond %{HTTP_USER_AGENT} "^WebDAV"        [OR]
RewriteCond %{HTTP_USER_AGENT} "^Microsoft Data Access Internet Publishing Provider" [OR]
RewriteCond %{HTTP_USER_AGENT} "^Microsoft Office" [OR]
RewriteCond %{HTTP_USER_AGENT} "^WebDrive"      [OR]
RewriteCond %{HTTP_USER_AGENT} "^iWorkHTTPKit"  [OR]
RewriteCond %{HTTP_USER_AGENT} "^gnome-vfs"     [OR]
RewriteCond %{HTTP_USER_AGENT} "^Dreamweaver-WebDAV-SCM1" [OR]
RewriteCond %{HTTP_USER_AGENT} "^BitKinex/"     [OR]
RewriteCond %{HTTP_USER_AGENT} "^cadaver/"      [OR]
RewriteCond %{HTTP_USER_AGENT} "^neon/"         [OR]
RewriteCond %{HTTP_USER_AGENT} "^Cyberduck/"    [OR]
RewriteCond %{HTTP_USER_AGENT} "^gvfs/"         [OR]
RewriteCond %{HTTP_USER_AGENT} "^Transmit"
RewriteCond %{REQUEST_URI} !^/webfolders$
RewriteCond %{REQUEST_URI} !^/webfolders/
RewriteRule ^/(.*) /webfolders/$1 [PT]

RewriteRule ^/webfolders$ /webfolders/ [R]
Alias /webfolders/ /var/www/htdocs/webfolders/
```

Clients who arrive at the server root over port 80 will now be untransparently
redirected to SSL over port 443, and transparently redirected from `/` to
`/webfolders/`.

Next comes the directory configuration. In the virtual host section of your
port 443 SSL server, put something along the following lines:

```apache
<Directory /var/www/htdocs/webfolders>

    # As our "cloud solution", this should be available from anywhere:
    Order allow,deny
    Allow from all

    AddDefaultCharset utf-8
    AddType application/x-httpd-php php php5
    DirectoryIndex server.php

    # Reroute all requests to subdirectories, or things that are not
    # physical files in the root directory, through server.php, which
    # happens to be a physical file in the root directory and thus exempt.
    # This also hides physical app directories like /data and /lib
    # from view.
    RewriteEngine On
    RewriteBase /webfolders
    RewriteCond %{REQUEST_URI} ^/webfolders/.*/.*$ [OR]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^.*$ /webfolders/server.php

</Directory>
```

The rewrite rules tell the server to send all requests to `server.php` that
contain two or more slashes (so are guaranteed to be virtual), and those not
referencing real files on disk, to `server.php` for processing. Since
`server.php` is itself a real file on disk, this breaks the loop and avoids
recursion. Resources such as `style.css` and `dir.png` also happen to be plain
files in the SambaDAV root, and are available normally.


## SambaDAV configuration

At startup, SambaDAV reads all PHP files in the `/config` directory. Each file
should return an array with config keys, like so:

```php
<?php

return array(
    'config_key' => 'config_value',
);
```

The order and number of files doesn't matter as long as the right keys are set,
but for convenience we supply four example config files.

The main configuration file is `/config/config.inc.php`. Please refer to the
comments in the file itself for more information. Some notes:

- `enable_webfolders` is the "master switch" for the whole SambaDAV suite. If
  this variable is not available *and* does not have the explicit value of
  `true`, then `server.php` does an early exit with a '404 Not found' error.
  The idea behind this is to create a single on/off switch for SambaDAV,
  through which administrators can centrally enable or disable the service.

- `server_basedir` is used by `server.php` to check whether the request was
  transparently rewritten from the root of the server. If the given URI does
  not start with `server_basedir`, `server.php` appends it during the setup of
  the SabreDAV server.

- `smbclient_extra_opts` is an optional string containing extra arguments to
  pass to `smbclient`. For example, you can specify a nonstandard port by
  adding `--port 6789`. The option string is pasted verbatim and unescaped at
  the end of the `smbclient` invocation.

- `anonymous_only` allows _only_ anonymous logins, and consequently bypasses
  the whole basic authentication logic, because credentials are no longer
  necessary. This setting is for people who allow guest access to their shares.

- `anonymous_allow` means that empty usernames and passwords are not rejected
  as they would normally be, but converted into an anonymous (guest) request.

  `anonymous_allow` causes seemingly strange behaviour when visiting SambaDAV
  in the browser. You might expect to get a password box, but instead you find
  that you're always logged in automatically as the anonymous user. That
  happens because the password box normally only appears after an initial
  unauthenticated request is countered by the server's 'authentication
  required' response. This instructs the browser to retry with a username and
  password. However, if anonymous logins are permitted, the server cannot tell
  the difference between someone attempting a true anonymous login and someone
  who should be sent an 'authentication required' response. So in the browser
  you always become the anonymous (guest) user.

  With a "real" WebDAV client, you can pass a username and password in the very
  first request and get authenticated access that way.

- `cache_use` enables the disk cache. If you set this to `false`, nothing will be
  cached and performance will be rather slow (everything's an expensive call to
  `smbclient`). Note that file contents are never cached, only metadata such as
  directory listings.

- `cache_dir` is the directory to use for caching. The default is
  `/dev/shm/webfolders`, which is a directory in `/dev/shm`, a filesystem in
  shared memory. The cache code will create this cache directory at runtime if
  it doesn't exist (and set the permissons correctly), so you don't have to
  create the directory first. See [this section](#caching) for more.

- `share_userhomes` is a bit of a misnomer, it's not a share but the name of
  the server on which the userhomes can be found. If this variable is not
  defined or is `false`, no userhome lookup is done. However, if it's set to
  the name of a server, then the share `//server/username` is added, where
  `username` is the name of the logged-in user. This userhome will appear in
  the SambaDAV root as a folder with the name of the logged-in user.

- `share_userhome_ldap` can be set to the name of a LDAP property containing
  the URL for the user's home share. The value of this property must be of the
  form `\\server` (which is interpreted as `\\server\username` for the
  currently logged-in user), or `\\server\share`. For instance, if all your
  LDAP users have a property called `sambaHomePath`, then its value will be
  used for the home share. This setting overrides `share_userhomes`, and only
  works with LDAP authentication.

The username that a user logs in with can be dissected as follows:

    workgroup\username@domain

      %w = workgroup
      %u = username
      %d = domain

The `%w`, `%u` and `%d` placeholders can be used in pattern strings. The
username is always available by definition, unless anonymous logins are
allowed. The other two placeholders can be undefined if the user didn't enter
them. If you specify a pattern that can't be filled from the user's input, the
application will abort the request. Patterns can be set to `null` or left
unspecified to use the default values.

- `userhome_pattern` contains a pattern to use for the userhomes. Example:
  `'//SERVER/%u'`. Defaults to `null` (no userhome derived from a pattern).

- `ldap_username_pattern` contains the pattern to use for the LDAP bind
  operation. Defaults to the (stripped) username for backwards compatibility.

- `samba_username_pattern` and `samba_domain_pattern` contain patterns to use
  for logging in to `smbclient`. They default to the username and `null`,
  respectively.

The other two config files are:

- `share_root.inc.php`: defines server/share pairs that show up in the server
  root under the name of the share;

- `share_extra.inc.php`: defines server/share pairs that show up in the server
  root as a folder with the name of the server(s), containing folders named
  after the share(s) on that server;

See the files themselves for specific examples and syntax.

An example of what `share_root.inc.php` can look like:

```php
<?php
return array(
    'share_root' => array(
        array('server1'),
        array('server2','share1'),
        array('server2','share2'),
    ),
);
```

`share_root` is an array of arrays. Each child array consists of one or two
strings. The first string is the name of a server, the second string is
optional and is the name of a share on that server. If you don't specify a
share name, SambaDAV will use a `smbclient -L` call to automatically retrieve a
list of available shares on that server (which may or may not be accessible to
the user).

`share_root` is so called because the shares that you specify here are placed
directly in the root as folders with the name of the *share*. In the example
above, the root folder would have multiple subfolders: a folder for each share
found on `server1`, and the folders `share1` and `share2`.

`share_extra` is like `share_root`, but shares and servers that you define in
that array are always placed in the SambaDAV root in a folder with the name of
the *server*, containing subfolders named after the shares.

```php
<?php
return array(
    'share_extra' => array(
        array('server5'),
        array('server6','share6').
    ),
);
```

In the example above, this would create two folders in the root named `server5`
and `server6`, with `server5` containing folders for all the autodiscovered
shares on that server, and `server6` containing a single subfolder called `share6`.

Further customization of the shares listing can be done by configuring Samba to
produce the required list of shares. SambaDAV is kept simple on purpose.


# User authentication

SambaDAV supports three methods of user authentication:

1. Anonymous access only;

2. Access through HTTP Basic authentication;

3. Access through HTTP Basic authentication, with an extra check against an
   LDAP server to see if the user is known and is allowed to access SambaDAV.

It is not possible to use Digest authentication, because SambaDAV needs the
user's plaintext username and password to pass on to `smbclient`. Using Basic
authentication is only secure over a HTTPS connection, so always make sure your
connection is properly encrypted!

SambaDAV does not have a user database of its own; it relies completely on
`smbclient` to authenticate and authorize the user. SambaDAV passes through
anything the user enters to `smbclient` for actual inspection. Smbclient is free
to accept or reject the login attempt according to its internal processes, and
SambaDAV will faithfully pass on the response.

The extra check against the LDAP server was written specially for the original
deployment environment, and might be too specific for general use. Since
version 0.4.0, SambaDAV supports two kinds of binds: the AD kind and the
regular kind, here called "fastbind". If the ldap host and basedn are not
provided in the config file, the code will try to get them from
`/etc/ldap.conf`.

In the fastbind scenario, the LDAP code will check whether the username and
password are valid by attempting to bind as the given user. If that succeeds,
and the config variable `ldap_groups` is not `false`, it checks whether the
user is a member of the given Posix group(s). If either condition fails, the
user is rejected.

In the AD bind scenario, the code will first bind with an auth DN, fetch the DN
for the user, and rebind as that user. It also optionally checks group
memberships.

There are three reasons for having LDAP support. Firstly, to put a lightweight
authentication service in front of the relatively heavyweight `smbclient`
method of authentication (spawning a process, interpreting the results and so
on). Secondly, to provide some form of access control. In this setup, only the
users who are members of a certain group can use SambaDAV. Thirdly, there is
the option to fetch the location of a user's home directory from an LDAP
property.


## Caching

Though the cache can be turned off in the config, you may find that the
performance becomes unacceptably slow, especially under Mac OS X. SambaDAV can
cache directory listings, file properties and other metadata on disk for
improved performance. The default cache location, changeable in the config, is
a directory in shared memory, under `/dev/shm`. SambaDAV will create this
directory with proper ownerships and permissions if it doesn't exist.

File *contents* are never cached. Only metadata such as directory listings are
cached. Metadata is first serialized, then zipped, then encrypted with a key
derived from the user's password.

The cache is automatically cleaned at certain intervals. A semaphore file is
used to avoid pulling the rug out from under active lookups. It should always
be safe to delete all the cache files (but hopefully unnecessary, since the
cache is self-cleaning).


## Logging

SambaDAV has a simple built-in logging mechanism that traces out the flow
through the program. The granularity is currently not great: trace logging can
be turned either 'off' or 'on'. The setting is in `/include/function.log.php`,
the `$trace_log` variable. Feel free to add `log_trace` statements wherever you
need them. Logging may become more pervasive in future releases.


## Client support

See also the SabreDAV documentation for details. Client support is mostly
something between the client and SabreDAV, not something that SambaDAV has
much influence on. However, there are some tips for getting WebDAV clients
running:

- All clients should connect over SSL as a matter of principle. Don't use an
  unencrypted connection, or you will expose passwords and file contents to the
  whole world.

- Windows XP needs a valid certificate. If the Network Drive mapper is not
  working, try surfing to the webfolders URL with Internet Explorer and see if
  you get a certificate error. Internet Explorer uses the same networking code
  as the builtin Webfolders client, and any errors you get in Internet Explorer
  will tell you what's going wrong in the Webfolders client.

- Windows XP does not support SSL connection syntax, or connecting to anything
  other than the root of a domain, but it does properly redirect to an SSL
  connection if it receives a redirect response from the server. So for proper
  Windows XP support, you must setup a plain HTTP server that redirects all
  requests to an SSL server with a valid SSL certificate. See the
  [Config](#webserver-configuration) section.


### Slow in Windows 7?

The default configuration of the built-in Windows 7 WebDAV client sometimes
makes network drives seem really slow to respond. A network share will take
ages to connect and opening folders takes many seconds. Luckily, getting a
snappy browsing experience is an easy fix:

1. Open Internet Explorer.
2. Open the `Tools` menu from the top menu bar.
3. Click `Internet Options`. A window opens.
4. In the window, open the `Connections` tab.
5. Click the button `LAN settings` near the bottom. A second window opens.
6. Uncheck the box marked `Automatically detect settings`.
7. Click `Ok` on both the windows to close them.

Try again and you should be able to connect and browse at normal speeds.

More information can be found in the [SabreDAV wiki](http://sabre.io/dav/clients).


## Security considerations

Always run this service over SSL! Your users are sending plaintext passwords,
which is only secure if the transport is encrypted.

Files inside the WebDAV tree (such as PHP files) are not interpreted or
executed by SambaDAV; they are streamed straight through `smbclient` to the
user in passthrough mode.

All operations on the server are run through `smbclient`, so users can do no
more than they already could by running `smbclient` on the server in a shell.
All permission checking is left to the SMB server.

The username and password are supplied to `smbclient` in plaintext through an
anonymous file descriptor (Unix pipe). This is reasonably secure (the passwords
don't show up in the process table and aren't written to disk), but in theory,
the data could be intercepted by other processes running as the same user. For
that reason, you should run this service under a dedicated user account.

`smbclient` comes with an ill-conceived shell-command "feature": users can
execute shell commands on the local server by sending `smbclient` a command
that starts with an exclamation mark; see the manpage. This is hardly useful
because the shell command is executed locally instead of remotely. The problem
is made worse by the fact that the exclamation mark is actually allowed in
Windows filenames, so it can't really be filtered out. However, characters from
0 to 31 are not allowed, and that range includes newlines. SambaDAV does filter
out (rather, forbid) characters in that range, so it's not easy to arrange for
an exclamation mark to appear at the start of a line.

We make no guarantees about the security or fitness for purpose of this
software. See the
[LICENSE](https://github.com/bokxing-it/sambadav/blob/master/LICENSE) for
specifics.


## Troubleshooting

- Is your `smbclient` install working correctly? Do you get the expected output
  when you execute:

```sh
# Lookup shares list as guest:
smbclient -N -L //myserver

# Lookup shares as specific user:
smbclient -U myuser -L //myserver

# Connect to a share as a specific user, get a file listing:
smbclient -U myuser //myserver/myshare -c 'ls'
```

  If these examples fail, try again with `--debuglevel=3` and carefully inspect
  the output for errors.

- If things are not working, try connecting to the server with a browser first.
  If your browser can't connect, or if you encounter certificate errors, fix
  those problems first. Only try WebDAV clients after getting SambaDAV to work
  in the browser.

- If nothing appears to happen (blank page), check the PHP error logs for error
  messages. SabreDAV requires a number of PHP extensions; you might need to
  enable some.

- Is `$enable_webfolders` set to the precise value of `true` in
  `/config/share_userhomes.inc.php`? That variable is the master switch; if
  it's set to anything but `true`, `server.php` bails out early with a 404 Not
  Found error.

- If you're getting errors, try checking the server logs. Pay attention to the
  resources requested and the HTTP reponse codes dished out by SambaDAV.  Is
  the client requesting the proper URL?

- Remember that SambaDAV is ultimately just a frontend to `smbclient`. If a
  user is granted no rights by `smbclient`, there's nothing SambaDAV can do.
  Turn on tracelogging to see the commands issued to `smbclient` and reproduce
  them on the commandline. Any unexpected errors?

- SambaDAV makes precise assumptions about the format of the `smbclient`
  output. Your `smbclient` might be producing different output from what
  SambaDAV expects.


## Reporting bugs

Bugs should be reported at the GitHub project page, in the issues section. This
requires a GitHub account. Alternatively, you can send an e-mail to the
authors; the e-mail addresses are found in the commit headers.

Please be specific about any problems you encounter. Turn on logging and try to
capture the commands and responses sent by SambaDAV and `smbclient`. (This
might require the insertion of some strategically placed `error_log()`
statements.) Include a mention of what you think the output should be.

## Upgrading

### From 0.4 to 0.5

If you want to migrate an older config, you probably need to add the new
`ldap_username_pattern` and `samba_username_pattern` config options. The
backwards-compatible value for these is `%u` (use the stripped username).

### From 0.3 to 0.4

The config system changed between 0.3 and 0.4. In 0.3, configuration was done
with a mix of global variables and defines. This changed in 0.4 to a system of
array keys which are merged into the main config from all PHP files found in
`/config`. The config variables and settings themselves have not changed, and
can be carried over quite easily from an 0.3 installation.
