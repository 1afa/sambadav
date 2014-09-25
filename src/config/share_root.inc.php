<?php	// $Format:SambaDAV: commit %h @ %cd$

// Configure the shares you would like to be visible in the SambaDAV root here.
// Any shares you configure show up in the root folder under the name of the
// *share*. If you want your shares to be contained in root folders named after
// the *server*, see share_extra.inc.php.
//
// $share_root is an array containing arrays with servers and shares. If you
// specify a server and a share, that share on that server will show up in the
// SambaDAV root under the name of the share. If you specify only a server
// name, SambaDAV will autodiscover the available shares on that server with a
// (cached) call to 'smbclient -L', and make all those shares available
// directly at the top level. E.g. if you want the following folder structure:
//
//   data
//   finance
//   SERVERTWO
//     web
//     photos
//
// ... you would configure the 'data' and 'finance' shares here, and the
// SERVERTWO shares in share_extra.inc.php.
//
// General idea:
//
//   return array(
//       'share_root' => array(
//           array('servername'),                // autodiscover all shares on the server
//           array('servername', 'sharename'),   // ...or hard-code a specific share
//           ...
//       ),
//   );
//
// Examples:
//
// - Autodiscover the shared disks on server MYSERVER, create toplevel folders
//   for each share found:
//
//     return array(
//         'share_root' => array(
//             array('MYSERVER'),
//         ),
//     );
//
// - Or don't autodiscover and show only the following shares on server MYSERVER,
//   creating three toplevel directories called 'data', 'archive' and 'finance':
//
//     return array(
//         'share_root' => array(
//             array('MYSERVER', 'data'),
//             array('MYSERVER', 'archive'),
//             array('MYSERVER', 'finance'),
//         ),
//     );
//
// - Or mix-and-match both approaches (but beware of name clashes between
//   shares on different servers: if both have a 'data' share, only the last one
//   found will be visible):
//
//     return array(
//         'share_root' => array(
//             array('MYSERVER', 'data'),
//             array('SERVERTWO', 'otherdata'),
//             array('SERVERTHREE'),
//         ),
//     );

return array();
