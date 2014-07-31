<?php	// $Format:SambaDAV: commit %h @ %cd$

# Copyright (C) 2013  Bokxing IT, http://www.bokxing-it.nl
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

class LDAP
{
	private $conn = false;
	private $host = false;
	private $basedn = false;
	public $userhome = false;

	public function verify ($user, $pass, $ldap_groups = false, $prop_userhome = false)
	{
		if ($user === false || $user === ''
		 || $pass === false || $pass === '') {
			return false;
		}
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
		// Suppress errors with the @ prefix because a bind error is *expected*
		// in case of a failed login; no need to pollute the error log:
		if (@ldap_bind($this->conn, sprintf('uid=%s,ou=Users,%s', $this->escape($user), $this->escape($this->basedn)), $pass) === false) {
			ldap_close($this->conn);
			return false;
		}
		if ($this->userSearch($user) === false) {
			ldap_close($this->conn);
			return false;
		}
		if ($this->groupSearch($user, $ldap_groups) === false) {
			ldap_close($this->conn);
			return false;
		}
		$this->userhomeSearch($user, $prop_userhome);
		ldap_close($this->conn);
		return true;
	}

	private function getParams ()
	{
		if ($this->host !== false && $this->basedn !== false) {
			return true;
		}
		if (($fp = fopen('/etc/ldap.conf', 'r')) === false) {
			return false;
		}
		while (($line = fgets($fp)) !== false) {
			if ($this->host === false) {
				if (preg_match('/^[Hh][Oo][Ss][Tt]\s+(\S*)\s*$/', $line, $matches) && isset($matches[1])) {
					$this->host = $matches[1];
				}
			}
			if ($this->basedn === false) {
				if (preg_match('/^[Bb][Aa][Ss][Ee]\s+(\S*)\s*$/', $line, $matches) && isset($matches[1])) {
					$this->basedn = $matches[1];
				}
			}
			if ($this->host !== false && $this->basedn !== false) {
				fclose($fp);
				return true;
			}
		}
		fclose($fp);
		return false;
	}

	private function userSearch ($user)
	{
		$searchdn = sprintf('ou=Users,%s', $this->escape($this->basedn));
		$filter = sprintf('(&(objectclass=posixAccount)(uid=%s))', $this->escape($user));

		if (($result = ldap_search($this->conn, $searchdn, $filter, array('uid'), 1)) === false) {
			return false;
		}
		return (ldap_first_entry($this->conn, $result) !== false);
	}

	private function groupSearch ($user, $groups)
	{
		if ($groups === false) {
			return true;
		}
		$searchdn = sprintf('ou=Groups,%s', $this->escape($this->basedn));
		$filter = sprintf('(&(memberUid=%s)(objectclass=posixGroup)%s)', $this->escape($user), $this->groupCNs($groups));

		if (($result = ldap_search($this->conn, $searchdn, $filter, array('memberUid'), 1)) === false) {
			return false;
		}
		return (ldap_first_entry($this->conn, $result) !== false);
	}

	private function userhomeSearch ($user, $prop_userhome)
	{
		if ($prop_userhome === false) {
			return true;
		}
		// If $prop_userhome is set, try to find the given property;
		// e..g. if $prop_userhome is 'sambaHomePath', search for that entry:
		$searchdn = sprintf('ou=Users,%s', $this->escape($this->basedn));
		$filter = sprintf('(&(objectclass=posixAccount)(uid=%s))', $this->escape($user));

		if (($result = ldap_search($this->conn, $searchdn, $filter, array($this->escape($prop_userhome)), 0)) === false) {
			return false;
		}
		if (($entry = ldap_first_entry($this->conn, $result)) === false) {
			return false;
		}
		if (($value = ldap_get_values($this->conn, $entry, $this->escape($prop_userhome))) === false) {
			return false;
		}
		if (!isset($value['count']) || $value['count'] == 0 || !isset($value[0]) || $value[0] === '') {
			return false;
		}
		$this->userhome = $value[0];
		return true;
	}

	private function groupCNs ($groups)
	{
		$esc = array();
		foreach ($groups as $group) {
			array_push($esc, sprintf('(cn=%s)', $this->escape($group)));
		}
		return '(|'.implode($esc).')';
	}

	private function escape ($str)
	{
		$bad = array('\\', '*', '(', ')', chr(0));
		$esc = array();
		foreach ($bad as $val) {
			$esc[] = sprintf('\\%02x', ord($val));
		}
		return str_replace($bad, $esc, $str);
	}
}
