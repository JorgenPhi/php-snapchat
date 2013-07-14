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

require_once 'snapchat_api.php';

/**
 * Extends the SnapchatAPI class to provide the API functions you'd expect.
 */
class Snapchat extends SnapchatAPI {
	/**
	 * Media type: Image.
	 */
	const MEDIA_IMAGE = 0;

	/**
	 * Media type: Video.
	 */
	const MEDIA_VIDEO = 1;

	/**
	 * Snap status: Sent.
	 */
	const STATUS_SENT = 0;

	/**
	 * Snap status: Delivered.
	 */
	const STATUS_DELIVERED = 1;

	/**
	 * Snap status: Opened.
	 */
	const STATUS_OPENED = 2;

	/**
	 * Snap status: Screenshot.
	 */
	const STATUS_SCREENSHOT = 3;

	/**
	 * Sets up some initial variables.
	 */
	public function __construct() {
		$this->auth_token = FALSE;
		$this->username = FALSE;
	}

	/**
	 * Handles login.
	 *
	 * @param $username
	 *   The username for the Snapchat account.
	 * @param $password
	 *   The password associated with the username.
	 *
	 * @return
	 *   The data returned by the service. Generally, returns the same result
	 *   as calling self::getUpdates().
	 */
	public function login($username, $password) {
		$timestamp = parent::timestamp();
		$result = parent::post(
			'/login',
			array(
 				'username' => $username,
				'password' => $password,
				'timestamp' => $timestamp,
			),
			array(
				parent::STATIC_TOKEN,
				$timestamp,
			)
		);

		// If the server sends back an auth token, remember it.
		if (!empty($result->auth_token)) {
			$this->auth_token = $result->auth_token;
		}

		// Store the logged in user.
		if (!empty($result->username)) {
			$this->username = $result->username;
		}

 		return $result;
	}

	/**
	 * Logs out the current user.
	 *
	 * @return
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function logout() {
		// Only allow authenticated users to log out.
		if (!$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/logout',
			array(
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

 	 	return is_null($result);
	}

	/**
 	 * Retrieves general user, friend, and snap updates.
	 *
	 * @param $since
	 *   (optional) The maximum age of the updates to be fetched in seconds
	 *   since epoch. Defaults to 0 because we generally want them all.
 	 *
 	 * @return
 	 *   The data returned by the service or FALSE on failure.
 	 */
	public function getUpdates($since = 0) {
		// Only allow authenticated users to get updates.
	 	if (!$this->username) {
	 		return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/updates',
			array(
				'timestamp' => $timestamp,
				'username' => $this->username,
				'update_timestamp' => $since,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		// If the server sends back an auth token, remember it.
 		if (!empty($result->auth_token)) {
			$this->auth_token = $result->auth_token;
		}

 		return $result;
	}

	/**
	 * Gets the user's snaps.
	 *
	 * @param $since
	 *   (optional) The maximum age of the snaps to be fetched in seconds
	 *   since epoch.
	 *
	 * @return
	 *   An array of snaps or FALSE on failure.
	 *
	 * @see self::getUpdates()
	 */
	public function getSnaps($since = 0) {
		$updates = self::getUpdates($since);

		if (!$updates) {
			return FALSE;
		}

		// We'll make these a little more readable.
		$snaps = array();
		foreach ($updates->snaps as $snap) {
			$snaps[] = (object) array(
				'id' => $snap->id,
				'media_id' => empty($snap->c_id) ? FALSE : $snap->c_id,
				'media_type' => $snap->m,
				'sender' => empty($snap->sn) ? $this->username : $snap->sn,
				'recipient' => empty($snap->rp) ? $this->username : $snap->rp,
				'status' => $snap->st,
				'screenshot_count' => empty($snap->c) ? 0 : $snap->c,
				'sent' => $snap->sts,
				'opened' => $snap->ts,
			);
		}

		return $snaps;
	}
}