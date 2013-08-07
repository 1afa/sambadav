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

namespace SambaDAV;

require_once 'common.inc.php';

class LDAP
{
	private $conn = FALSE;
	private $host = FALSE;
	private $basedn = FALSE;

	public function verify ($user, $pass, $ldap_groups = FALSE)
	{
		if (FALSE($user) || $user === ''
		 || FALSE($pass) || $pass === '') {
			return FALSE;
		}
		if (FALSE($this->getParams())
		 || FALSE($this->conn = ldap_connect($this->host))) {
			return FALSE;
		}
		// Suppress errors with the @ prefix because a bind error is *expected*
		// in case of a failed login; no need to pollute the error log:
		if (FALSE(ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3))
		 || FALSE(@ldap_bind($this->conn, sprintf('uid=%s,ou=Users,%s', $this->escape($user), $this->escape($this->basedn)), $pass))
		 || FALSE($this->userSearch($user))
		 || FALSE($this->groupSearch($user, $ldap_groups))) {
			ldap_close($this->conn);
			return FALSE;
		}
		ldap_close($this->conn);
		return TRUE;
	}

	private function getParams ()
	{
		if (!FALSE($this->host) && !FALSE($this->basedn)) {
			return TRUE;
		}
		if (FALSE($fp = fopen('/etc/ldap.conf', 'r'))) {
			return FALSE;
		}
		while (!FALSE($line = fgets($fp))) {
			if (FALSE($this->host)) {
				if (preg_match('/^[Hh][Oo][Ss][Tt]\s+(\S*)\s*$/', $line, $matches) && isset($matches[1])) {
					$this->host = $matches[1];
				}
			}
			if (FALSE($this->basedn)) {
				if (preg_match('/^[Bb][Aa][Ss][Ee]\s+(\S*)\s*$/', $line, $matches) && isset($matches[1])) {
					$this->basedn = $matches[1];
				}
			}
			if (!FALSE($this->host) && !FALSE($this->basedn)) {
				break;
			}
		}
		fclose($fp);
		return (!FALSE($this->host) && !FALSE($this->basedn));
	}

	private function userSearch ($user)
	{
		$searchdn = sprintf('ou=Users,%s', $this->escape($this->basedn));
		$filter = sprintf('(&(objectclass=posixAccount)(uid=%s))', $this->escape($user));

		return (!FALSE($result = ldap_search($this->conn, $searchdn, $filter, array('uid'), 1))
		     && !FALSE(ldap_first_entry($this->conn, $result)));
	}

	private function groupSearch ($user, $groups)
	{
		if (FALSE($groups)) {
			return TRUE;
		}
		$searchdn = sprintf('ou=Groups,%s', $this->escape($this->basedn));
		$filter = sprintf('(&(memberUid=%s)(objectclass=posixGroup)%s)', $this->escape($user), $this->groupCNs($groups));

		return (!FALSE($result = ldap_search($this->conn, $searchdn, $filter, array('memberUid'), 1))
		     && !FALSE(ldap_first_entry($this->conn, $result)));
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
