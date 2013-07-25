<?php

// This variable MUST be set to TRUE for Webfolders to work; server.php will
// exit with a 404 error if this variable has any other value than TRUE.
// Rationale is to have a single master on/off switch for the Webfolders service:
$enable_webfolders = TRUE;

// If you set this variable to the name of the server that contains the
// userhomes, Webfolders will add the user's home directory to the topmost list
// of directories. For example, if your server is called 'JUPITER', setting
// $share_userhomes to the string 'JUPITER' and logging in with user 'john'
// will auto-add the share 'john' on 'JUPITER' to the list of shares.
$share_userhomes = FALSE;
