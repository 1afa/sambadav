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

namespace SambaDAV;

class LDAP
{
	public $method = null;
	public $host = null;
	public $basedn = null;
	public $userhome = null;

	public $authdn = null;
	public $authpass = null;

	private $conn = null;

	const METHOD_FASTBIND = 1;
	const METHOD_BIND = 2;
	const CONFIG_FILE = '/etc/ldap.conf';

	public function
	__construct ($method = self::METHOD_FASTBIND, $host = null, $basedn = null, $authdn = null, $authpass = null)
	{
		$this->method = $method;
		$this->host = $host;
		$this->basedn = $basedn;
		$this->authdn = $authdn;
		$this->authpass = $authpass;
	}

	public function
	__destruct ()
	{
		if (is_resource($this->conn)) {
			ldap_close($this->conn);
		}
	}

	public function
	connect ()
	{
		if ($this->getParams() === false) {
			return false;
		}
		if (($this->conn = ldap_connect($this->host)) === false) {
			return false;
		}
		if (ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3) === false) {
			ldap_close($this->conn);
			return false;
		}
		if ($this->method === self::METHOD_BIND) {
			// To search on Active Directory, we must set this option or we
			// get an "Operations error":
			ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);
		}
		return true;
	}

	public function
	verify ($user, $pass, $ldap_groups = null, $prop_userhome = null)
	{
		if ($user === false || $user === ''
		 || $pass === false || $pass === '') {
			return false;
		}
		if ($this->connect() === false) {
			return false;
		}
		if ($this->method === self::METHOD_FASTBIND) {
			$ret = $this->verify_fastbind($user, $pass, $ldap_groups, $prop_userhome);
			ldap_close($this->conn);
			return $ret;
		}
		if ($this->method === self::METHOD_BIND) {
			$ret = $this->verify_bind($user, $pass, $ldap_groups, $prop_userhome);
			ldap_close($this->conn);
			return $ret;
		}
		ldap_close($this->conn);
		return false;
	}

	private function
	verify_fastbind ($user, $pass, $ldap_groups, $prop_userhome)
	{
		// Suppress errors with the @ prefix because a bind error is *expected*
		// in case of a failed login; no need to pollute the error log:
		$searchdn = sprintf('ou=Users,%s', $this->escape($this->basedn));

		if (@ldap_bind($this->conn, sprintf('uid=%s,%s', $this->escape($user), $searchdn), $pass) === false) {
			return false;
		}
		if ($this->groupSearch($user, $ldap_groups) === false) {
			return false;
		}
		// Find the userhome share:
		$filter = sprintf('(&(objectclass=posixAccount)(uid=%s))', $this->escape($user));
		$this->userhomeSearch($user, $searchdn, $filter, $prop_userhome);

		return true;
	}

	private function
	verify_bind ($user, $pass, $ldap_groups, $prop_userhome)
	{
		// Start by binding with the auth account:
		if (@ldap_bind($this->conn, $this->authdn, $this->authpass) === false) {
			return false;
		}
		// We are now bound under the auth account; lookup the user's DN:
		$userfilter = sprintf('(&(objectclass=USER)(SAMACCOUNTNAME=%s))', $this->escape($user));

		if (($result = ldap_search($this->conn, $this->basedn, $userfilter)) === false) {
			return false;
		}
		if (($entry = ldap_first_entry($this->conn, $result)) === false) {
			return false;
		}
		if (($userdn = ldap_get_dn($this->conn, $entry)) === false) {
			return false;
		}
		// $userdn now contains the user's actual DN.
		// Now we do a fastbind with this userdn:

		if (@ldap_bind($this->conn, $userdn, $pass) === false) {
			return false;
		}
		// Check that the user is a member of the given group(s):
		if (is_array($ldap_groups)) {
			$esc = array();
			foreach ($ldap_groups as $group) {
				$esc[] = sprintf('(&(objectclass=GROUP)(SAMACCOUNTNAME=%s))', $this->escape($group));
			}
			$groupfilter = sprintf('(&(|%s)(member=%s))', implode($esc), $userdn);
		}
		else if (is_string($ldap_groups)) {
			$groupfilter = sprintf('(&(&(objectclass=GROUP)(SAMACCOUNTNAME=%s))(member=%s))', $this->escape($ldap_groups), $userdn);
		}
		else {
			// No groups specified? Find userhome:
			$this->userhomeSearch($user, $userdn, $userfilter, $prop_userhome);
			return true;
		}
		if (($result = ldap_search($this->conn, $this->basedn, $groupfilter, array('member'), 1)) === false) {
			return false;
		}
		if (ldap_first_entry($this->conn, $result) === false) {
			return false;
		}
		// Find the userhome share:
		$this->userhomeSearch($user, $userdn, $userfilter, $prop_userhome);
		return true;
	}

	public function
	getParams ()
	{
		if ($this->host !== null && $this->basedn !== null) {
			return true;
		}
		if (($fp = fopen(self::CONFIG_FILE, 'r')) === false) {
			return false;
		}
		while (($line = fgets($fp)) !== false) {
			if ($this->host === null) {
				if (preg_match('/^[Hh][Oo][Ss][Tt]\s+(\S*)\s*$/', $line, $matches) && isset($matches[1])) {
					$this->host = $matches[1];
				}
			}
			if ($this->basedn === null) {
				if (preg_match('/^[Bb][Aa][Ss][Ee]\s+(\S*)\s*$/', $line, $matches) && isset($matches[1])) {
					$this->basedn = $matches[1];
				}
			}
			if ($this->host !== null && $this->basedn !== null) {
				fclose($fp);
				return true;
			}
		}
		fclose($fp);
		return false;
	}

	private function
	groupSearch ($user, $groups)
	{
		// For Fastbind:
		if ($groups === null) {
			return true;
		}
		$searchdn = sprintf('ou=Groups,%s', $this->escape($this->basedn));
		$filter = sprintf('(&(memberUid=%s)(objectclass=posixGroup)%s)', $this->escape($user), $this->groupCNs($groups));

		if (($result = ldap_search($this->conn, $searchdn, $filter, array('memberUid'), 1)) === false) {
			return false;
		}
		return (ldap_first_entry($this->conn, $result) !== false);
	}

	private function
	userhomeSearch ($user, $searchdn, $filter, $prop_userhome)
	{
		if ($prop_userhome === null) {
			return;
		}
		// If $prop_userhome is set, try to find the given property;
		// e..g. if $prop_userhome is 'sambaHomePath', search for that entry:
		if (($result = ldap_search($this->conn, $searchdn, $filter, array($prop_userhome), 0)) === false) {
			return;
		}
		if (($entry = ldap_first_entry($this->conn, $result)) === false) {
			return;
		}
		if (($value = ldap_get_values($this->conn, $entry, $prop_userhome)) === false) {
			return;
		}
		if (!isset($value['count']) || $value['count'] == 0 || !isset($value[0]) || $value[0] === '') {
			return;
		}
		$this->userhome = $value[0];
	}

	public function
	groupCNs ($groups)
	{
		// For Fastbind:
		if (is_string($groups)) {
			return sprintf('(cn=%s)', $this->escape($groups));
		}
		$esc = array();
		foreach ($groups as $group) {
			array_push($esc, sprintf('(cn=%s)', $this->escape($group)));
		}
		return '(|'.implode($esc).')';
	}

	private function
	escape ($str)
	{
		$bad = array('\\', '*', '(', ')', chr(0));
		$esc = array();
		foreach ($bad as $val) {
			$esc[] = sprintf('\\%02x', ord($val));
		}
		return str_replace($bad, $esc, $str);
	}
}
