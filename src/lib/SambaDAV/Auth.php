<?php	// $Format:SambaDAV: commit %h @ %cd$

# Copyright (C) 2014  Bokxing IT, http://www.bokxing-it.nl
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

use Sabre\HTTP;

class Auth
{
	public $user = null;
	public $pass = null;

	private $baseuri;
	private $config;
	private $userhome = null;	// An URI instance

	public function
	__construct ($config, $baseuri = '/')
	{
		$this->config = $config;
		$this->baseuri = $baseuri;
	}

	public function
	exec ()
	{
		// If ANONYMOUS_ONLY is set to true in the config, don't require credentials;
		// also the 'logout' action makes no sense for an anonymous server:
		if ($this->config->anonymous_only) {
			return true;
		}
		$auth = new HTTP\BasicAuth();
		$auth->setRealm('Web Folders');

		// If no basic auth creds set, but the variables "user" and "pass" were
		// posted to the page (e.g. from a/the login form), substitute those:
		if (!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['PHP_AUTH_PW'])) {
			if (isset($_POST) && isset($_POST['user']) && isset($_POST['pass'])) {
				$_SERVER['PHP_AUTH_USER'] = $_POST['user'];
				$_SERVER['PHP_AUTH_PW'] = $_POST['pass'];

				// HACK: dynamically change the request method to GET, because
				// otherwise SambaDAV will throw an exception because there is
				// no POST handler installed. This change causes SabreDAV to
				// process this request just like any other basic auth login:
				$_SERVER['REQUEST_METHOD'] = 'GET';
			}
		}
		list($this->user, $this->pass) = $auth->getUserPass();

		if ($this->user === false || $this->user === '') $this->user = null;
		if ($this->pass === false || $this->pass === '') $this->pass = null;

		// If you're tagged with 'logout' but you're not passing a
		// username/pass, redirect to plain index:
		if (isset($_GET['logout']) && ($this->user === null || $this->pass === null)) {
			header("Location: {$this->baseuri}");
			return false;
		}
		// Otherwise, if you're tagged with 'logout', make sure the authentication is refused,
		// to make the browser flush its cache:
		if (isset($_GET['logout']) || ($this->config->anonymous_allow === false && ($this->user === null || $this->pass === null))) {
			$auth->requireLogin();
			$loginForm = new LoginForm($this->baseuri);
			echo $loginForm->getBody();
			return false;
		}
		// If we allow anonymous logins, and we did not get all creds, skip authorization:
		if ($this->config->anonymous_allow && ($this->null === null || $this->pass === null)) {
			return true;
		}
		// Set userhome to userhome pattern if defined:
		if ($this->user !== null) {
			if ($this->config->share_userhomes) {
				$this->userhome = new URI($this->config->share_userhomes, $this->user);
			}
		}
		if ($this->checkLdap($auth) === false) {
			return false;
		}
		return true;
	}

	private function
	checkLdap ($auth)
	{
		// Check LDAP for group membership:
		// $ldap_groups is sourced from config/config.inc.php:
		if ($this->config->ldap_auth === false) {
			return true;
		}
		$ldap = new LDAP();

		if ($ldap->verify($this->ldapUsername(), $this->pass, $this->config->ldap_groups, $this->config->share_userhome_ldap) === false) {
			sleep(2);
			$auth->requireLogin();
			$loginForm = new LoginForm($this->baseuri);
			echo $loginForm->getBody();
			return false;
		}
		if ($ldap->userhome !== false) {
			$this->userhome = new URI($ldap->userhome);
		}
		return true;
	}

	public function
	ldapUsername ()
	{
		return $this->user;
	}

	public function
	sambaUsername ()
	{
		return $this->user;
	}

	public function
	getUserhome ()
	{
		return $this->userhome;
	}
}
