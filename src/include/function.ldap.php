<?php	// $Id: function.ldap.php,v 1.12 2013/07/23 16:04:33 alfred Exp $
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

require_once 'common.inc.php';

function ldap_verify ($user, $pass, $ldap_groups)
{
	if (FALSE($user) || $user === ''
	 || FALSE($pass) || $pass === '') {
		return FALSE;
	}
	if (FALSE(list($host, $basedn) = ldap_get_params())
	 || FALSE($conn = ldap_connect($host))) {
		return FALSE;
	}
	if (FALSE(@ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3))
	 || FALSE(@ldap_bind($conn, sprintf('uid=%s,ou=Users,%s', ldap_escape($user), ldap_escape($basedn)), $pass))
	 || FALSE(ldap_user_search($conn, $basedn, $user))
	 || FALSE(ldap_group_search($conn, $basedn, $user, $ldap_groups))) {
		ldap_close($conn);
		return FALSE;
	}
	ldap_close($conn);
	return TRUE;
}

function ldap_get_params ()
{
	static $host = FALSE;
	static $basedn = FALSE;

	if (!FALSE($host) && !FALSE($basedn)) {
		return array($host, $basedn);
	}
	if (FALSE($fp = fopen('/etc/ldap.conf', 'r'))) {
		return FALSE;
	}
	while (!FALSE($line = fgets($fp)) && (FALSE($host) || FALSE($basedn))) {
		if (FALSE($host) && preg_match('/^[Hh][Oo][Ss][Tt]\s+(\S*)\s*$/', $line, $matches) && isset($matches[1])) {
			$host = $matches[1];
		}
		else if (FALSE($basedn) && preg_match('/^[Bb][Aa][Ss][Ee]\s+(\S*)\s*$/', $line, $matches) && isset($matches[1])) {
			$basedn = $matches[1];
		}
	}
	fclose($fp);
	return (FALSE($host) || FALSE($basedn)) ? FALSE : array($host, $basedn);
}

function ldap_user_search ($conn, $basedn, $user)
{
	$searchdn = sprintf('ou=Users,%s', ldap_escape($basedn));
	$filter = sprintf('(&(objectclass=posixAccount)(uid=%s))', ldap_escape($user));

	return (!FALSE($result = @ldap_search($conn, $searchdn, $filter, array('uid'), 1))
	     && !FALSE(ldap_first_entry($conn, $result)));
}

function ldap_group_search ($conn, $basedn, $user, $ldap_groups)
{
	if (FALSE($ldap_groups)) {
		return TRUE;
	}
	$searchdn = sprintf('ou=Groups,%s', ldap_escape($basedn));
	$filter = sprintf('(&(memberUid=%s)(objectclass=posixGroup)%s)', ldap_escape($user), ldap_group_cns($ldap_groups));

	return (!FALSE($result = @ldap_search($conn, $searchdn, $filter, array('memberUid'), 1))
	     && !FALSE(ldap_first_entry($conn, $result)));
}

function ldap_group_cns ($ldap_groups)
{
	$esc = array();
	foreach ($ldap_groups as $ldap_group) {
		array_push($esc, sprintf('(cn=%s)', ldap_escape($ldap_group)));
	}
	return '(|'.implode($esc).')';
}

function ldap_escape ($str)
{
	$bad = array('\\', '*', '(', ')', chr(0));
	$esc = array();
	foreach ($bad as $val) {
		$esc[] = sprintf('\\%02x', ord($val));
	}
	return str_replace($bad, $esc, $str);
}
