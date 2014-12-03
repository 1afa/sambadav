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
# Project page: <https://github.com/bokxing-it/sambadav/>

namespace SambaDAV\Log;

class Filesystem extends \SambaDAV\Log
{
	private $filename = null;

	public function
	__construct ($level = parent::WARN, $filename = null)
	{
		$this->level = $level;

		if (is_null($this->filename = $filename)) {
			$this->filename = strftime(dirname(dirname(dirname(dirname(__FILE__)))).'/log/%Y-%m-%d.log');
		}
	}

	protected function
	commit ($level, $message)
	{
		if ($fp = $this->fileOpenLockAppend()) {
			fwrite($fp, $message);
			$this->fileCloseUnlock($fp);
			return true;
		}
		return false;
	}

	private function
	fileOpenLockAppend ()
	{
		// Open the file for appending, lock it.
		// Returns file handle, or false on error.
		if (($fd = fopen($this->filename, 'a')) === false) {
			return false;
		}
		if ((flock($fd, LOCK_EX)) === false) {
			fclose($fd);
			return false;
		}
		chmod($this->filename, 0600);
		return $fd;
	}

	private function
	fileCloseUnlock ($fd)
	{
		fflush($fd);
		flock($fd, LOCK_UN);
		fclose($fd);
	}
}
