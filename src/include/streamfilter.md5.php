<?php	// $Format:SambaDAV: commit %h @ %cd$
/*
 * Copyright (C) 2013, 2014 Bokxing IT, http://www.bokxing-it.nl
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

// This is a dummy class that does nothing except hold the value of the hash.
// We need this dummy class to save the final value of the hash when the filter
// is done. The caller creates a new instance of this class and passes it as a
// parameter to stream_filter_append. Because objects are passed by reference,
// the filter can update the hash value, and the caller can read out the value
// when the streaming is done. Saves us from using a global variable.

class MD5FilterOutput
{
	public $hash = '';
}

class MD5Filter extends \php_user_filter
{
	private $ctx;

	public function
	onCreate ()
	{
		$this->ctx = hash_init('md5');
		return true;
	}

	public function
	onClose ()
	{
		// $this->params points to an instance of MD5FilterOutput,
		// owned by caller, that caller will use to read out the hash:
		$this->params->hash = hash_final($this->ctx);
		return true;
	}

	public function
	filter ($in, $out, &$consumed, $closing)
	{
		while ($bucket = stream_bucket_make_writeable($in)) {
			hash_update($this->ctx, $bucket->data);
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
		}
		return PSFS_PASS_ON;
	}
}
