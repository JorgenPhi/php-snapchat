<?php

/**
 * @file
 *   Provides the Snapchat class with a lower-level API layer to handle
 *   requests and decrypt responses.
 */
abstract class SnapchatAgent {

	/*
	 * App version (as of 2013-11-20). Before updating this value, confirm
	 * that the library requests everything in the same way as the app.
	 */
	const VERSION = '4.1.07';

	/*
	 * The API URL. We're using the /bq endpoint, the one that the iPhone
	 * uses. Android clients still seem to be using the /ph endpoint.
	 *
	 * @todo
	 *   Make library capable of using different endpoints (some of the
	 *   resource names are different, so they aren't interchangeable).
	 */
	const URL = 'https://feelinsonice-hrd.appspot.com/bq';

	/*
	 * The API secret. Used to create access tokens.
	 */
	const SECRET = 'iEk21fuwZApXlz93750dmW22pw389dPwOk';

	/*
	 * The static token. Used when no session is available.
	 */
	const STATIC_TOKEN = 'm198sOkJEn37DjqZ32lpRu76xmw288xSQ9';

	/*
	 * The blob encryption key. Used to encrypt and decrypt media.
	 */
	const BLOB_ENCRYPTION_KEY = 'M02cnQ51Ji97vwT4';

	/*
	 * The hash pattern.
	 *
	 * @see self::hash()
	 */
	const HASH_PATTERN = '0001110111101110001111010101111011010001001110011000110001000110'; // Hash pattern

	/**
	 * Default cURL options. It doesn't appear that the UA matters, but
	 * authenticity, right?
	 */
	public static $CURL_OPTIONS = array(
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_TIMEOUT => 10,
		CURLOPT_USERAGENT => 'Snapchat/4.1.07 (Nexus 4; Android 18; gzip)',
	);

	/**
	 * Returns the current timestamp.
	 *
	 * @return int
	 *   The current timestamp, expressed in milliseconds since epoch.
	 */
	public function timestamp() {
		return intval(microtime(TRUE) * 1000);
	}

	/**
	 * Pads data using PKCS5.
	 *
	 * @param data $data
	 *   The data to be padded.
	 * @param int $blocksize
	 *   The block size to pad to. Defaults to 16.
	 *
	 * @return data
	 *   The padded data.
	 */
	public function pad($data, $blocksize = 16) {
		$pad = $blocksize - (strlen($data) % $blocksize);
		return $data . str_repeat(chr($pad), $pad);
	}

	/**
	 * Decrypts blob data for standard images and videos.
	 *
	 * @param data $data
	 *   The data to decrypt.
	 *
	 * @return data
	 *   The decrypted data.
	 */
	public function decryptECB($data) {
		return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, self::BLOB_ENCRYPTION_KEY, self::pad($data), MCRYPT_MODE_ECB);
	}

	/**
	 * Encrypts blob data for standard images and videos.
	 *
	 * @param data $data
	 *   The data to encrypt.
	 *
	 * @return data
	 *   The encrypted data.
	 */
	public function encryptECB($data) {
		return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, self::BLOB_ENCRYPTION_KEY, self::pad($data), MCRYPT_MODE_ECB);
	}

	/**
	 * Decrypts blob data for stories.
	 *
	 * @param data $data
	 *   The data to decrypt.
	 * @param string $key
	 *   The base64-encoded key.
	 * @param string $iv
	 *   $iv The base64-encoded IV.
	 *
	 * @return data
	 *   The decrypted data.
	 */
	public function decryptCBC($data, $key, $iv) {
		// Decode the key and IV.
		$iv = base64_decode($iv);
		$key = base64_decode($key);

		// Decrypt the data.
		$data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_CBC, $iv);
		$padding = ord($data[strlen($data) - 1]);

		return substr($data, 0, -$padding);
	}

	/**
	 * Implementation of Snapchat's hashing algorithm.
	 *
	 * @param string $first
	 *   The first value to use in the hash.
	 * @param string $second
	 *   The second value to use in the hash.
	 *
	 * @return string
	 *   The generated hash.
	 */
	public function hash($first, $second) {
		// Append the secret to the values.
		$first = self::SECRET . $first;
		$second = $second . self::SECRET;

		// Hash the values.
		$hash = hash_init('sha256');
		hash_update($hash, $first);
		$hash1 = hash_final($hash);
		$hash = hash_init('sha256');
		hash_update($hash, $second);
		$hash2 = hash_final($hash);

		// Create a new hash with pieces of the two we just made.
		$result = '';
		for ($i = 0; $i < strlen(self::HASH_PATTERN); $i++) {
			$result .= substr(self::HASH_PATTERN, $i, 1) ? $hash2[$i] : $hash1[$i];
		}

		return $result;
	}

	/**
	 * Checks to see if a blob looks like a media file.
	 *
	 * @param data $data
	 *   The blob data (or just the header).
	 *
	 * @return bool
	 *   TRUE if the blob looks like a media file, FALSE otherwise.
	 */
	function isMedia($data) {
		// Check for a JPG header.
		if ($data[0] == chr(0xFF) && $data[1] == chr(0xD8)) {
			return TRUE;
		}

		// Check for a MP4 header.
		if ($data[0] == chr(0x00) && $data[1] == chr(0x00)) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Checks to see if a blob looks like a compressed file.
	 *
	 * @param data $data
	 *   The blob data (or just the header).
	 *
	 * @return bool
	 *   TRUE if the blob looks like a compressed file, FALSE otherwise.
	 */
	function isCompressed($data) {
		// Check for a PK header.
		if ($data[0] == chr(0x50) && $data[1] == chr(0x4B)) {
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Uncompress the blob and put the data into an Array.
	 * 	Array(
	 * 		overlay~zip-CE6F660A-4A9F-4BD6-8183-245C9C75B8A0	=> overlay_file_data,
	 *		media~zip-CE6F660A-4A9F-4BD6-8183-245C9C75B8A0		=> m4v_file_data
	 * 	)
	 *
	 * @param data $data
	 *   The blob data (or just the header).
	 *
	 * @return array
	 *   Array containing both file contents, or FALSE if couldn't extract.
	 */
	function unCompress($data) {
		if (!file_put_contents("./temp", $data)) {
			exit('Should have write access to own folder');
		}
		$resource = zip_open("./temp");
		$result = FALSE;
		if (is_resource($resource)) {
			while($zip_entry = zip_read($resource)) {
				$filename = zip_entry_name($zip_entry);
				if (zip_entry_open($resource, $zip_entry, "r")) {
					$result[$filename] = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
					zip_entry_close($zip_entry);
				} else {
					return FALSE;
				}
			}
			zip_close($resource);
		}
		
		return $result;
	}

	/**
	 * Performs a GET request. Currently only used for story blobs.
	 *
	 * @todo
	 *   cURL-ify this and maybe combine with the post function.
	 *
	 * @param string $endpoint
	 *   The address of the resource being requested (e.g. '/story_blob' or
	 *   '/story_thumbnail').
	 *
	 * @return data
	 *   The retrieved data.
	 */
	public function get($endpoint) {
		return file_get_contents(self::URL . $endpoint);
	}

	/**
	 * Performs a POST request. Used for pretty much everything.
	 *
	 * @todo
	 *   Replace the blob endpoint check with a more robust check for
	 *   application/octet-stream.
	 *
	 * @param string $endpoint
	 *   The address of the resource being requested (e.g. '/update_snaps' or
	 *   '/friend').
	 * @param array $data
	 *   An dictionary of values to send to the API. A request token is added
	 *   automatically.
	 * @param array $params
	 *   An array containing the parameters used to generate the request token.
	 * @param bool $multipart
	 *   If TRUE, sends the request as multipart/form-data. Defaults to FALSE.
	 *
	 * @return mixed
	 *   The data returned from the API (decoded if JSON). Returns FALSE if
	 *   the request failed.
	 */
	public function post($endpoint, $data, $params, $multipart = FALSE) {
		$ch = curl_init();

		$data['req_token'] = self::hash($params[0], $params[1]);
		$data['version'] = self::VERSION;

		if (!$multipart) {
			$data = http_build_query($data);
		}

		$options = self::$CURL_OPTIONS + array(
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_URL => self::URL . $endpoint,
		);
		curl_setopt_array($ch, $options);

		$result = curl_exec($ch);

		// If cURL doesn't have a bundle of root certificates handy, we provide
		// ours (see http://curl.haxx.se/docs/sslcerts.html).
		if (curl_errno($ch) == 60) {
			curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/ca_bundle.crt');
			$result = curl_exec($ch);
		}

		// If the cURL request fails, return FALSE. Also check the status code
		// since the API generally won't return friendly errors.
		if ($result === FALSE || curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
			curl_close($ch);
			return FALSE;
		}

		curl_close($ch);

		if ($endpoint == '/blob') {
			return $result;
		}

		// Add support for foreign characters in the JSON response.
		$result = iconv('UTF-8', 'UTF-8//IGNORE', utf8_encode($result));

		$data = json_decode($result);
		return json_last_error() == JSON_ERROR_NONE ? $data : FALSE;
	}

}
