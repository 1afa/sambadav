<?php	// $Format:SambaDAV: commit %h @ %cd$

// This variable MUST be set to true for SambaDAV to work; server.php will exit
// with a 404 error if this variable has any other value than true. Rationale
// is to have a single master on/off switch for the SambaDAV service:
$enable_webfolders = true;

// If you set this variable to the name of the server that contains the
// userhomes, SambaDAV will add the user's home directory to the topmost list
// of directories. For example, if your server is called 'JUPITER', setting
// $share_userhomes to the string 'JUPITER' and logging in with user 'john'
// will auto-add the share 'john' on 'JUPITER' to the list of shares.
$share_userhomes = false;

// If this variable is set to the name of a LDAP property, then that property
// will be queried for the location of the user's home. Example: if every user
// has an LDAP property called 'sambaHomePath', then by setting the variable
// below to 'sambaHomePath', SambaDAV will read the value of that property and
// use it as the home directory. Currently, the value in LDAP must look like:
//   \\servername            (will be expanded to \\servername\username)
//   \\servername\sharename
$share_userhome_ldap = false;
