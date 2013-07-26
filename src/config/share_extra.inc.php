<?php	// $Format:SambaDAV: commit %h @ %cd$

// This file contains share definitions in the same syntax as
// share_root.inc.php, but the difference is that shares defined here are
// placed in a root folder with the name of the *server* that the share is on.
// This is useful if you have shares with the same name on different servers.
// For example, if you want the following folder structure:
//
//   SERVERONE
//     data
//     finance
//   SERVERTWO
//     data
//     finance
//
// ...you would use:
//
//   $share_extra = array(
//       array('SERVERONE', 'data'),
//       array('SERVERONE', 'finance'),
//       array('SERVERTWO', 'data'),
//       array('SERVERTWO', 'finance'),
//   );

$share_extra = array();
