<?php

include_once dirname(__FILE__) . '/snapchat_agent.php';
include_once dirname(__FILE__) . '/snapchat_cache.php';

/**
 * @file
 *   Provides an implementation of the undocumented Snapchat API.
 */
class Snapchat extends SnapchatAgent {

	/**
	 * The media types for snaps from confirmed friends.
	 */
	const MEDIA_IMAGE = 0;
	const MEDIA_VIDEO = 1;
	const MEDIA_VIDEO_NOAUDIO = 2;

	/**
	 * The media type for a friend request (not technically media, but it
	 * shows up in the feed).
	 */
	const MEDIA_FRIEND_REQUEST = 3;

	/**
	 * The media types for snaps from unconfirmed friends.
	 */
	const MEDIA_FRIEND_REQUEST_IMAGE = 4;
	const MEDIA_FRIEND_REQUEST_VIDEO = 5;
	const MEDIA_FRIEND_REQUEST_VIDEO_NOAUDIO = 6;

	/**
	 * Snap statuses.
	 */
	const STATUS_NONE = -1;
	const STATUS_SENT = 0;
	const STATUS_DELIVERED = 1;
	const STATUS_OPENED = 2;
	const STATUS_SCREENSHOT = 3;

	/**
	 * Friend statuses.
	 */
	const FRIEND_CONFIRMED = 0;
	const FRIEND_UNCONFIRMED = 1;
	const FRIEND_BLOCKED = 2;
	const FRIEND_DELETED = 3;

	/**
	 * Privacy settings.
	 */
	const PRIVACY_EVERYONE = 0;
	const PRIVACY_FRIENDS = 1;

	/**
	 * Sets up some initial variables. If a username and password are passed in,
	 * we attempt to log in. If a username and auth token are passed in, we'll
	 * bypass the login process and use those values.
	 *
	 * @param string $username
	 *   The username for the Snapchat account.
	 * @param string $password
	 *   The password associated with the username, if logging in.
	 * @param string $auth_token
	 *   The auth token, if already logged in.
	 */
	public function __construct($username = NULL, $password = NULL, $auth_token = NULL) {
		$this->auth_token = FALSE;
		$this->username = FALSE;

		if (!empty($password)) {
			$this->login($username, $password);
		}
		elseif (!empty($auth_token)) {
			$this->auth_token = $auth_token;
			$this->cache = new SnapchatCache();
			$this->username = $username;
		}
	}

	/**
	 * Handles login.
	 *
	 * @param string $username
	 *   The username for the Snapchat account.
	 * @param string $password
	 *   The password associated with the username.
	 *
	 * @return mixed
	 *   The data returned by the service or FALSE if the request failed.
	 *   Generally, returns the same result as self::getUpdates().
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

		// If the login is successful, set the username and auth_token.
		if (isset($result->logged) && $result->logged) {
			$this->auth_token = $result->auth_token;
			$this->username = $result->username;

			$this->cache = new SnapchatCache();
			$this->cache->set('updates', $result);

			return $result;
		}
		else {
			return FALSE;
		}
	}

	/**
	 * Logs out the current user.
	 *
	 * @return bool
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

		// Clear out the cache in case the instance is recycled.
		$this->cache = NULL;

		return is_null($result);
	}

	/**
	 * Creates a user account.
	 *
	 * @todo
	 *   Add better validation.
	 *
	 * @param string $username
	 *   The desired username.
	 * @param string $password
	 *   The password to associate with the account.
	 * @param string $email
	 *   The email address to associate with the account.
	 * @param $birthday string
	 *   The user's birthday (yyyy-mm-dd).
	 *
	 * @return mixed
	 *   The data returned by the service or FALSE if registration failed.
	 *   Generally, returns the same result as calling self::getUpdates().
	 */
	public function register($username, $password, $email, $birthday) {
		$timestamp = parent::timestamp();
		$result = parent::post(
			'/register',
			array(
				'birthday' => $birthday,
				'password' => $password,
				'email' => $email,
				'timestamp' => $timestamp,
			),
			array(
				parent::STATIC_TOKEN,
				$timestamp,
			)
		);

		if (!isset($result->token)) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/registeru',
			array(
				'email' => $email,
				'username' => $username,
				'timestamp' => $timestamp,
			),
			array(
				parent::STATIC_TOKEN,
				$timestamp,
			)
		);

		// If registration is successful, set the username and auth_token.
		if (isset($result->logged) && $result->logged) {
			$this->auth_token = $result->auth_token;
			$this->username = $result->username;

			$this->cache = new SnapchatCache();
			$this->cache->set('updates', $result);

			return $result;
		}
		else {
			return FALSE;
		}
	}

	/**
	 * Retrieves general user, friend, and snap updates.
	 *
	 * @param bool $force
	 *   Forces an update even if there's fresh data in the cache. Defaults
	 *   to FALSE.
	 *
	 * @return mixed
	 *   The data returned by the service or FALSE on failure.
	 */
	public function getUpdates($force = FALSE) {
		if (!$force) {
			$result = $this->cache->get('updates');
			if ($result) {
				return $result;
			}
		}

		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/all_updates',
			array(
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		if (!empty($result->updates_response)) {
			$this->auth_token = $result->updates_response->auth_token;
			$this->cache->set('updates', $result->updates_response);
			return $result->updates_response;
		}

		return $result;
	}

	/**
	 * Gets the user's snaps.
	 *
	 * @return mixed
	 *   An array of snaps or FALSE on failure.
	 */
	public function getSnaps() {
		$updates = $this->getUpdates();

		if (empty($updates)) {
			return FALSE;
		}

		// We'll make these a little more readable.
		$snaps = array();
		foreach ($updates->snaps as $snap) {
			$snaps[] = (object) array(
				'id' => $snap->id,
				'media_id' => empty($snap->c_id) ? FALSE : $snap->c_id,
				'media_type' => $snap->m,
				'time' => empty($snap->t) ? FALSE : $snap->t,
				'sender' => empty($snap->sn) ? $this->username : $snap->sn,
				'recipient' => empty($snap->rp) ? $this->username : $snap->rp,
				'status' => $snap->st,
				'screenshot_count' => empty($snap->c) ? 0 : $snap->c,
				'sent' => $snap->sts,
				'opened' => $snap->ts,
				'broadcast' => empty($snap->broadcast) ? FALSE : (object) array(
					'url' => $snap->broadcast_url,
					'action_text' => $snap->broadcast_action_text,
					'hide_timer' => $snap->broadcast_hide_timer,
				),
			);
		}

		return $snaps;
	}

	/**
	 * Gets friends' stories.
	 *
	 * @param bool $force
	 *   Forces an update even if there's fresh data in the cache. Defaults
	 *   to FALSE.
	 *
	 * @return mixed
	 *   An array of stories or FALSE on failure.
	 */
	function getFriendStories($force = FALSE) {
		if (!$force) {
			$result = $this->cache->get('stories');
			if ($result) {
				return $result;
			}
		}

		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/all_updates',
			array(
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		if (!empty($result->stories_response)) {
			$this->cache->set('stories', $result->stories_response);
		}
		else {
			return FALSE;
		}

		$stories = array();
		foreach ($result->stories_response->friend_stories as $group) {
			foreach ($group->stories as $story) {
				$stories[] = $story->story;
			}
		}

		return $stories;
	}

	/**
	 * Queries the friend-finding service.
	 *
	 * @todo
	 *   If over 30 numbers are passed in, spread the query across multiple
	 *   requests. The API won't return more than 30 results at once.
	 *
	 * @param array $numbers
	 *   An array of phone numbers.
	 * @param string $country
	 *   The country code. Defaults to US.
	 *
	 * @return mixed
	 *   An array of user objects or FALSE on failure.
	 */
	public function findFriends($numbers, $country = 'US') {
		$batches = array_chunk(array_flip($numbers), 30, TRUE);

		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$results = array();
		foreach ($batches as $batch) {
			$timestamp = parent::timestamp();
			$result = parent::post(
				'/find_friends',
				array(
					'countryCode' => $country,
					'numbers' => json_encode($batch),
					'timestamp' => $timestamp,
					'username' => $this->username,
				),
				array(
					$this->auth_token,
					$timestamp,
				)
			);

			if (isset($result->results)) {
				$results = $results + $result->results;
			}
		}

		return $results;
	}

	/**
	 * Gets the user's friends.
	 *
	 * @return mixed
	 *   An array of friends or FALSE on failure.
	 */
	public function getFriends() {
		$updates = $this->getUpdates();

		if (empty($updates)) {
			return FALSE;
		}

		return $updates->friends;
	}

	/**
	 * Gets the user's added friends.
	 *
	 * @return mixed
	 *   An array of friends or FALSE on failure.
	 */
	public function getAddedFriends() {
		$updates = $this->getUpdates();

		if (empty($updates)) {
			return FALSE;
		}

		return $updates->added_friends;
	}

	/**
	 * Adds a friend.
	 *
	 * @param string $username
	 *   The username of the friend to add.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function addFriend($username) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/friend',
			array(
				'action' => 'add',
				'friend' => $username,
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		// Sigh...
		if (strpos($result->message, 'Sorry! Couldn\'t find') === 0) {
			return FALSE;
		}

		return !empty($result->message);
	}

	/**
	 * Adds multiple friends.
	 *
	 * @param array $usernames
	 *   Usernames of friends to add.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function addFriends($usernames) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$friends = array();
		foreach ($usernames as $username) {
			$friends[] = (object) array(
				'display' => '',
				'name' => $username,
				'type' => self::FRIEND_UNCONFIRMED,
			);
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/friend',
			array(
				'action' => 'multiadddelete',
				'friend' => json_encode(array(
					'friendsToAdd' => $friends,
					'friendsToDelete' => array(),
				)),
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		return !empty($result->message);
	}

	/**
	 * Deletes a friend.
	 *
	 * @todo
	 *   Investigate deleting multiple friends at once.
	 *
	 * @param string $username
	 *   The username of the friend to delete.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function deleteFriend($username) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/friend',
			array(
				'action' => 'delete',
				'friend' => $username,
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		return !empty($result->message);
	}

	/**
	 * Deletes multiple friends.
	 *
	 * @param array $usernames
	 *   Usernames of friends to delete.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function deleteFriends($usernames) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/friend',
			array(
				'action' => 'multiadddelete',
				'friend' => json_encode(array(
					'friendsToAdd' => array(),
					'friendsToDelete' => $usernames,
				)),
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		return !empty($result->message);
	}

	/**
	 * Sets a friend's display name.
	 *
	 * @param string $username
	 *   The username of the user to modify.
	 * @param string $display
	 *   The new display name.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function setDisplayName($username, $display) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/friend',
			array(
				'action' => 'display',
				'display' => $display,
				'friend' => $username,
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		return !empty($result->message);
	}

	/**
	 * Blocks a user.
	 *
	 * @param string $username
	 *   The username of the user to be blocked.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function block($username) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/friend',
			array(
				'action' => 'block',
				'friend' => $username,
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		return !empty($result->message);
	}

	/**
	 * Unblocks a user.
	 *
	 * @param string $username
	 *   The username of the user to unblock.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function unblock($username) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/friend',
			array(
				'action' => 'unblock',
				'friend' => $username,
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		return !empty($result->message);
	}


	/**
	 * Downloads a snap.
	 *
	 * @param string $id
	 *   The snap ID.
	 *
	 * @return mixed
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

		if (parent::isMedia(substr($result, 0, 2))) {
			return $result;
		}
		else {
			$result = parent::decryptECB($result);

			if (parent::isMedia(substr($result, 0, 2))) {
				return $result;
			}
		}

		return FALSE;
	}

	/**
	 * Sends event information to Snapchat.
	 *
	 * @param array $events
	 *   An array of events. This seems to be used only to report usage data.
	 * @param array $snap_info
	 *   Data to send along in addition to the event array. This is used to
	 *   mark snaps as viewed. Defaults to an empty array.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
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
	 * @param string $id
	 *   The snap to mark as viewed.
	 * @param int $time
	 *   The amount of time (in seconds) the snap was viewed. Defaults to 1.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
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
	 * Sends a screenshot event.
	 *
	 * @param string $id
	 *   The snap to mark as shot.
	 * @param int $time
	 *   The amount of time (in seconds) the snap was viewed. Defaults to 1.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function markSnapShot($id, $time = 1) {
		$snap_info = array(
			$id => array(
				// We use the same time values as in markSnapViewed, but add in the
				// screenshot status.
				't' => microtime(TRUE),
				'sv' => $time + (mt_rand() / mt_getrandmax() / 10),
				'c' => self::STATUS_SCREENSHOT,
			),
		);

		$events = array(
			array(
				'eventName' => 'SNAP_SCREENSHOT',
				'params' => array(
					'id' => $id,
				),
				'ts' => time() - $time,
			),
		);

		return $this->sendEvents($events, $snap_info);
	}

	/**
	 * Uploads a snap.
	 *
	 * @todo
	 *   Fix media ID generation; it looks like they're GUIDs now.
	 *
	 * @param int $type
	 *   The media type, i.e. MEDIA_IMAGE or MEDIA_VIDEO.
	 * @param data $data
	 *   The file data to upload.
	 *
	 * @return mixed
	 *   The ID of the uploaded media or FALSE on failure.
	 */
	public function upload($type, $data) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		// To make cURL happy, we write the data to a file first.
		$temp = tempnam(sys_get_temp_dir(), 'Snap');
		file_put_contents($temp, parent::encryptECB($data));

		if (version_compare(PHP_VERSION, '5.5.0', '>=')) {
			$cfile = curl_file_create($temp, ($type == self::MEDIA_IMAGE ? 'image/jpeg' : 'video/quicktime'), 'snap');
		}

		$media_id = strtoupper($this->username) . '~' . time();
		$timestamp = parent::timestamp();
		$result = parent::post(
			'/upload',
			array(
				'media_id' => $media_id,
				'type' => $type,
				'data' => (version_compare(PHP_VERSION, '5.5.0', '>=') ? $cfile : '@' . $temp . ';filename=data'),
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			),
			TRUE
		);

		unlink($temp);

		return is_null($result) ? $media_id : FALSE;
	}

	/**
	 * Sends a snap.
	 *
	 * @param string $media_id
	 *   The media ID of the snap to send.
	 * @param array $recipients
	 *   An array of recipient usernames.
	 * @param int $time
	 *   The time in seconds the snap should be available (1-10). Defaults to 3.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function send($media_id, $recipients, $time = 3) {
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
	 * Sets a story.
	 *
	 * @param string $media_id
	 *   The media ID of the story to set.
	 * @param int $media_type
	 *   The media type of the story to set (i.e. MEDIA_IMAGE or MEDIA_VIDEO).
	 * @param int $time
	 *   The time in seconds the story should be available (1-10). Defaults to 3.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function setStory($media_id, $media_type, $time = 3) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/post_story',
			array(
				'client_id' => $media_id,
				'media_id' => $media_id,
				'time' => $time,
				'timestamp' => $timestamp,
				'type' => $media_type,
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
	 * Downloads a story.
	 *
	 * @param string $media_id
	 *   The media ID of the story.
	 * @param string $key
	 *   The base64-encoded key of the story.
	 * @param string $iv
	 *   The base64-encoded IV of the story.
	 *
	 * @return mixed
	 *   The story data or FALSE on failure.
	 */
	public function getStory($media_id, $key, $iv) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		// Retrieve encrypted story and decrypt.
		$blob = parent::get('/story_blob?story_id=' . $media_id);

		if (!empty($blob)) {
			return parent::decryptCBC($blob, $key, $iv);
		}

		return FALSE;
	}

	/**
	 * Downloads a story's thumbnail.
	 *
	 * @param string $media_id
	 *   The media_id of the story.
	 * @param string $key
	 *   The base64-encoded key of the story.
	 * @param string $iv
	 *   The base64-encoded IV of the thumbnail.
	 *
	 * @return mixed
	 *   The thumbnail data or FALSE on failure.
	 */
	public function getStoryThumb($media_id, $key, $iv) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		// Retrieve encrypted story and decrypt.
		$blob = parent::get('/story_thumbnail?story_id=' . $media_id);

		if (!empty($blob)) {
			return parent::decryptCBC($blob, $key, $iv);
		}

		return FALSE;
	}

	/**
	 * Marks a story as viewed.
	 *
	 * @param string $id
	 *   The ID of the story.
	 * @param int $screenshot_count
	 *   Amount of times screenshotted. Defaults to 0.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function markStoryViewed($id, $screenshot_count = 0) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		// Mark story as viewed.
		$timestamp = parent::timestamp();
		$result = parent::post(
			'/update_stories',
			array(
				'friend_stories' => json_encode(array(
					array(
						'id' => $id,
						'screenshot_count' => $screenshot_count,
						'timestamp' => $timestamp,
					),
				)),
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
	 * @param array $friends
	 *   An array of usernames for which to retrieve best friend information.
	 *
	 * @return mixed
	 *   An dictionary of friends by username or FALSE on failure.
	 */
	public function getBests($friends) {
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
	 * Clears the current user's feed.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function clearFeed() {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/clear',
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
	 * Updates the current user's privacy setting.
	 *
	 * @param int $setting
	 *   The privacy setting, i.e. PRIVACY_EVERYONE or PRIVACY_FRIENDS.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function updatePrivacy($setting) {
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

		return isset($result->param) && $result->param == $setting;
	}

	/**
	 * Updates the current user's email address.
	 *
	 * @param string $email
	 *   The new email address.
	 *
	 * @return bool
	 *   TRUE if successful, FALSE otherwise.
	 */
	public function updateEmail($email) {
		// Make sure we're logged in and have a valid access token.
		if (!$this->auth_token || !$this->username) {
			return FALSE;
		}

		$timestamp = parent::timestamp();
		$result = parent::post(
			'/settings',
			array(
				'action' => 'updateEmail',
				'email' => $email,
				'timestamp' => $timestamp,
				'username' => $this->username,
			),
			array(
				$this->auth_token,
				$timestamp,
			)
		);

		return isset($result->param) && $result->param == $email;
	}

}
