<?php

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
	 * Privacy setting: Accept snaps from everyone.
	 */
	const PRIVACY_EVERYONE = 0;

	/**
	 * Privacy setting: Accept snaps only from friends.
	 */
	const PRIVACY_FRIENDS = 1;

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
		// Make sure we're logged in and have a valid access token.
	 	if (!$this->auth_token || !$this->username) {
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
		// Make sure we're logged in and have a valid access token.
	 	if (!$this->auth_token || !$this->username) {
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
		$updates = $this->getUpdates($since);

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

	/**
	 * Downloads a snap.
	 *
	 * @param $id
	 *   The snap ID.
	 *
	 * @return
	 *   The snap data or FALSE on failure.
	 */
	public function getMedia($id) {
		// Make sure we're logged in and have a valid access token.
	 	if (!$this->auth_token || !$this->username) {
	 		return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/blob',
			array(
				'id' => $id,
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		if (parent::is_media(substr($result, 0, 2))) {
			return $result;
		}
		else {
			$result = parent::decrypt($result);

			if (parent::is_media(substr($result, 0, 2))) {
				return $result;
			}
		}

		return FALSE;
	}

	/**
	 * Sends event information to Snapchat.
	 *
	 * @param $events
	 *   An array of events to send to Snapchat (generally usage data).
	 * @param $snap_info
	 *   (optional) Data to send along in addition to the event array. This is
	 *   used by the app to mark snaps as viewed. Defaults to an empty object.
	 *
	 * @return
	 *   TRUE on success, FALSE on failure.
	 *
	 * @see self::markSnapViewed()
	 */
	public function sendEvents($events, $snap_info = array()) {
		// Make sure we're logged in and have a valid access token.
	 	if (!$this->auth_token || !$this->username) {
	 		return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/update_snaps',
			array(
				'events' => json_encode($events),
				'json' => json_encode($snap_info),
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
	 * Marks a snap as viewed.
	 *
	 * Snaps can be downloaded an (apparently) unlimited amount of times before
	 * they are viewed. Once marked as viewed, they are deleted.
	 *
	 * It's worth noting that it seems possible to mark others' snaps as viewed
	 * as long as you know the ID. This hasn't been tested thoroughly, but it
	 * could be useful if you send a snap that you immediately regret.
	 *
	 * @param $id
	 *   The snap to mark as viewed.
	 * @param $time
	 *   The amount of time (in seconds) the snap was viewed. Defaults to 1.
	 *
	 * @return
	 *   TRUE on success, FALSE on failure.
	 */
	public function markSnapViewed($id, $time = 1) {
		$snap_info = array(
			$id => array(
				// Here Snapchat saw fit to use time as a float instead of
				// straight milliseconds.
				't' => microtime(TRUE),
				// We add a small variation here just to make it look more
				// realistic.
				'sv' => $time + (mt_rand() / mt_getrandmax() / 10),
			),
		);

		$events = array(
			array(
				'eventName' => 'SNAP_VIEW',
				'params' => array(
					'id' => $id,
					// There are others, but it wouldn't be worth the effort to
					// put them in here since they likely don't matter.
				),
				'ts' => time() - $time,
			),
			array(
				'eventName' => 'SNAP_EXPIRED',
				'params' => array(
					'id' => $id,
				),
				'ts' => time()
			),
		);

		return $this->sendEvents($events, $snap_info);
	}

	/**
	 * Uploads a file.
	 *
	 * @param $type
	 *   The media type, i.e. MEDIA_IMAGE or MEDIA_VIDEO.
	 * @param $data
	 *   The file data to upload.
	 *
	 * @return
	 *   The media ID or FALSE on failure.
	 */
	function upload($type, $data) {
		// Make sure we're logged in and have a valid access token.
	 	if (!$this->auth_token || !$this->username) {
	 		return FALSE;
		}

		// To make cURL happy, we write the data to a file first.
		$temp = tempnam(sys_get_temp_dir(), 'Snap');
		file_put_contents($temp, parent::encrypt($data));

		// For the adventurous: What happens when you upload more than one snap
		// per second?
		$media_id = strtoupper($this->username) . time();
		$timestamp = parent::timestamp();
		$result = parent::post(
			'/upload',
			array(
				'media_id' => $media_id,
				'type' => $type,
				'data' => '@' . $temp . ';filename=data',
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			),
			TRUE
		);

		return is_null($result) ? $media_id : FALSE;
	}

	/**
	 * Sends a snap.
	 *
	 * @param $media_id
	 *   The media ID of the snap to send.
	 * @param $recipients
	 *   An array of recipients.
	 * @param $time
	 *   (optional) The time in seconds the snap should be available (1-10).
	 *   Defaults to 3.
	 *
	 * @return
	 *   TRUE on success or FALSE on failure.
	 */
	function send($media_id, $recipients, $time = 3) {
		// Make sure we're logged in and have a valid access token.
	 	if (!$this->auth_token || !$this->username) {
	 		return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/send',
			array(
				'media_id' => $media_id,
				'recipient' => implode(',', $recipients),
				'time' => $time,
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
	 * Gets the best friends and scores of the specified users.
	 *
	 * @param $friends
	 *   An array of usernames of the friends for which to retrieve best friend
	 *   information.
	 *
	 * @return
	 *   An associative array keyed by username or FALSE on failure.
	 */
	function getBests($friends) {
		// Make sure we're logged in and have a valid access token.
	 	if (!$this->auth_token || !$this->username) {
	 		return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/bests',
			array(
				'friend_usernames' => json_encode($friends),
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		if (empty($result)) {
			return FALSE;
		}

		$friends = array();
		foreach((array) $result as $friend => $bests) {
			$friends[$friend] = (array) $bests;
		}

		return $friends;
	}

	/**
	 * Updates the current user's privacy setting.
	 *
	 * @param $setting
	 *   The privacy setting, i.e. PRIVACY_EVERYONE or PRIVACY_FRIENDS.
	 *
	 * @return
	 *   TRUE on success or FALSE on failure.
	 */
	function updatePrivacy($setting) {
		// Make sure we're logged in and have a valid access token.
	 	if (!$this->auth_token || !$this->username) {
	 		return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/settings',
			array(
				'action' => 'updatePrivacy',
				'privacySetting' => $setting,
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		return $result->param == $setting;
	}
}