<?php

/**
 * Copyright (c) 2013 Daniel Stelljes
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * A lower-level class that handles requests to the Snapchat API.
 */
abstract class SnapchatAPI {
	/**
	 * App version.
	 */
	const VERSION = '5.0.1';

	/**
	 * API URL.
	 */
	const URL = 'https://feelinsonice-hrd.appspot.com/bq';

	/**
	 * API secret.
	 */
	const SECRET = 'iEk21fuwZApXlz93750dmW22pw389dPwOk';

	/**
	 * API static token.
	 */
	const STATIC_TOKEN = 'm198sOkJEn37DjqZ32lpRu76xmw288xSQ9';

	/**
	 * Blob encryption key.
	 */
	const BLOB_ENCRYPTION_KEY = 'M02cnQ51Ji97vwT4';

	/**
	 * Hash pattern.
	 */
	const HASH_PATTERN = '0001110111101110001111010101111011010001001110011000110001000110';

	/**
	 * Default curl options.
	 */
	public static $CURL_OPTIONS = array(
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_TIMEOUT => 10,
		CURLOPT_USERAGENT => 'Snapchat/5.0.1 CFNetwork/609.1.4 Darwin/13.0.0',
	);

	/**
	 * Decrypts blob data.
	 *
	 * @param $data
	 *   The data to decrypt.
	 *
	 * @return
	 *   The decrypted data.
	 *
	 * @see SnapchatAPI::encrypt()
	 */
	public function decrypt($data) {
		mcrypt_decrypt(MCRYPT_RIJNDAEL_128, self::BLOB_ENCRYPTION_KEY, $data, MCRYPT_MODE_ECB);
	}

	/**
	 * Encrypts blob data.
	 *
	 * @param $data
	 *   The data to encrypt.
	 *
	 * @return
	 *   The encrypted data.
	 *
	 * @see SnapchatAPI::decrypt()
	 */
	public function encrypt($data) {
		mcrypt_encrypt(MCRYPT_RIJNDAEL_128, self::BLOB_ENCRYPTION_KEY, $data, MCRYPT_MODE_ECB);
	}

	/**
	 * Implementation of Snapchat's obscure hashing function.
	 *
	 * @param $first
	 *   The first value to use in the hash.
	 * @param $second
	 *   The second value to use in the hash.
	 *
	 * @return
	 *   The hash.
	 */
	public function hash($first, $second) {
		// Append the secret to the values.
		$first = self::SECRET . $first;
		$second = $second . self::SECRET;

		// Hash the values.
		$hash1 = hash_init('sha256');
		hash_update($hash1, $first);
		$hash2 = hash_init('sha256');
		hash_update($hash2, $second);

		// Create a new hash with pieces of the two we just made.
		$pattern = self::HASH_PATTERN;
		$result;
		for ($i = 0; $i < strlen(self::HASH_PATTERN); $i++) {
			$result += $pattern[$i] ? $hash2[$i] : $hash1[$i];
		}

		return $result;
	}

	/**
	 * Runs a POST request against the API.
	 *
	 * Snapchat appears to only use POST for API requests, so this is really
	 * the only function used to query the API.
	 *
	 * @param $endpoint
	 *   The address of the resource being requested (e.g. '/update_snaps' or
	 *   '/friend').
	 * @param $data
	 *   An associative array of values to send to the API. A request token is
	 *   added automatically.
	 * @param $params
	 *   An array containing the parameters used to generate the request token.
	 *
	 * @return
	 *   The data returned from the API (decoded if JSON).
	 */
	public function post($endpoint, $data, $params) {
		$ch = curl_init();

		$options = self::$CURL_OPTIONS + array(
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_URL => self::URL . $endpoint,
		);
		curl_setopt_array($ch, $options);

		$data['req_token'] = $this->hash($params[0], $params[1]);

		$result = curl_exec($ch);
		if ($result === FALSE) {
			// TODO: error handling
		}
		curl_close($ch);

		$data = json_decode($result);
		return json_last_error() == JSON_ERROR_NONE ? $data : $result;
	}
}