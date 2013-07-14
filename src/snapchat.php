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
 	 * Sets up some initial variables.
 	 */
 	public function __construct() {
 		$this->auth_token = FALSE;
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
 	 *   as calling self::update().
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
 		if (is_array($result) && !empty($result['auth_token'])) {
 			$this->auth_token = $result['auth_token'];
 			unset($result['auth_token']);
 		}

 		return $result;
 	}
 }