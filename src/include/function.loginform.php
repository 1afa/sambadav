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

function print_login_form ($baseuri)
{
	?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
  <head>
    <title>SambaDAV Login</title>
    <link rel="stylesheet" type="text/css" href="<?php echo $baseuri ?>style.css"/>
    <style type="text/css">
      body {
        font-family: sans-serif;
        font-size: normal;
      }
      form {
        margin: 0px;
        padding: 0px;
      }
      table {
        width: auto;
        margin: auto;
        margin-top: 10%;
        background-color: #e8e8e8;
        border: 1px solid #c4c4cb;
        padding: 0.6em;
      }
      img {
        display: block;
        margin: auto;
        margin-top: 1em;
      }
    </style>
    <script type="text/javascript">
	function basicauth ()
	{
		// Do a behind-the-scenes basic auth login to the page with the
		// credentials from the form; this will cause them to be cached
		// by the browser and resent for the rest of the session.

		var u = document.getElementById('user');
		var p = document.getElementById('pass');
		var d = document.getElementById('spin');
		var ajax = null;

		if (!u || !p) return false;

		if (window.XMLHttpRequest) {
			ajax = new XMLHttpRequest();
		}
		else if (window.ActiveXObject) {
			ajax = new ActiveXObject("Microsoft.XMLHTTP");
		}
		else {
			return false;
		}
		// Clean location without query parameters:
		var cleanloc = document.location.protocol + '//' + document.location.host + document.location.pathname;

		ajax.onreadystatechange = function() {
			if (ajax.readyState == 4) {
				if (ajax.status == 200) {
					window.location = cleanloc;
				}
				else {
					if (ajax.status == 401) {
						p.value = '';
					}
					d.innerHTML = '&nbsp;';
				}
			}
		}
		// Show spinner:
		d.innerHTML = '<img src="<?php echo $baseuri ?>spinner.gif" alt=""/>';

		ajax.open('GET', cleanloc, true, u.value, p.value);
		ajax.send(null);
	}
    </script>
  </head>
  <body>
    <form method="post" onsubmit="javascript:basicauth(); return false;">
      <table align="center">
        <tr>
          <td><label for="user">username</label></td>
          <td><input type="text" id="user" name="user"/></td>
        </tr>
        <tr>
          <td><label for="pass">password</label></td>
          <td><input type="password" id="pass" name="pass"/></td>
        </tr>
        <tr>
          <td><div id="spin">&nbsp;</div></td>
          <td><input type="submit"/></td>
        </tr>
      </table>
      <center><img src="<?php echo $baseuri ?>logo-sambadav.png" alt="SambaDAV"/></center>
    </form>
  </body>
</html>
<?php

}
