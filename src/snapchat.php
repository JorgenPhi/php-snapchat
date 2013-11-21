<?php

/**
 * Provides an implementation of the undocumented Snapchat API.
 */
class Snapchat {

  const VERSION = '6.0.2'; // App version
  const URL = 'https://feelinsonice-hrd.appspot.com/bq'; // API URL
  const SECRET = 'iEk21fuwZApXlz93750dmW22pw389dPwOk'; // API secret
  const STATIC_TOKEN = 'm198sOkJEn37DjqZ32lpRu76xmw288xSQ9'; // API static token
  const BLOB_ENCRYPTION_KEY = 'M02cnQ51Ji97vwT4'; // Blob encryption key
  const HASH_PATTERN = '0001110111101110001111010101111011010001001110011000110001000110'; // Hash pattern
  const MEDIA_IMAGE = 0; // Media type: Image
  const MEDIA_VIDEO = 1; // Media type: Video
  const MEDIA_VIDEO_NOAUDIO = 2; // Media type: Video without audio
  const MEDIA_FRIEND_REQUEST = 3; // Media type: Friend request
  const MEDIA_FRIEND_REQUEST_IMAGE = 4; // Media type: Image from unconfirmed friend
  const MEDIA_FRIEND_REQUEST_VIDEO = 5; // Media type: Video from unconfirmed friend
  const MEDIA_FRIEND_REQUEST_VIDEO_NOAUDIO = 6; // Media type: Video without audio from unconfirmed friend
  const STATUS_NONE = -1; // Snap status: None
  const STATUS_SENT = 0; // Snap status: Sent
  const STATUS_DELIVERED = 1; // Snap status: Delivered
  const STATUS_OPENED = 2; // Snap status: Opened
  const STATUS_SCREENSHOT = 3; // Snap status: Screenshot
  const FRIEND_CONFIRMED = 0; // Friend status: Confirmed
  const FRIEND_UNCONFIRMED = 1; // Friend status: Unconfirmed
  const FRIEND_BLOCKED = 2; // Friend status: Blocked
  const FRIEND_DELETED = 3; // Friend status: Deleted
  const PRIVACY_EVERYONE = 0; // Privacy setting: Accept snaps from everyone
  const PRIVACY_FRIENDS = 1; // Privacy setting: Accept snaps only from friends


  /**
   * Sets up some initial variables.
   */
  public function __construct($username = NULL, $password = NULL) {
    $this->auth_token = FALSE;
    $this->username = FALSE;

    if (!empty($username)) $this->login($username, $password);
  }


  /**
   * Default curl options.
   */
  public static $CURL_OPTIONS = array(
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'Snapchat/6.0.2 (iPhone; iOS 7.0.4; gzip)',
  );


  /**
   * Returns the current timestamp.
   *
   * @return The current timestamp, expressed in milliseconds since epoch.
   */
  public function timestamp() {
    return intval(microtime(TRUE) * 1000);
  }


  /**
   * Pads data using PKCS5.
   *
   * @param $data The data to be padded.
   * @param $blocksize The block size to pad to. Defaults to 16.
   * @return The padded data.
   */
  public function pad($data, $blocksize = 16) {
    $pad = $blocksize - (strlen($data) % $blocksize);
    return $data . str_repeat(chr($pad), $pad);
  }


  /**
   * Decrypts blob data.
   *
   * @param $data The data to decrypt.
   * @return The decrypted data.
   */
  public function decrypt($data) {
    return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, self::BLOB_ENCRYPTION_KEY, self::pad($data), MCRYPT_MODE_ECB);
  }


  /**
   * Encrypts blob data.
   *
   * @param $data The data to encrypt.
   * @return The encrypted data.
   */
  public function encrypt($data) {
    return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, self::BLOB_ENCRYPTION_KEY, self::pad($data), MCRYPT_MODE_ECB);
  }


  /**
   * Implementation of Snapchat's obscure hashing algorithm.
   *
   * @param $first The first value to use in the hash.
   * @param $second The second value to use in the hash.
   * @return The hash.
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
   * @param $blob The blob data (or just the header).
   * @return TRUE if the blob looks like a media file, FALSE otherwise.
   */
  function isMedia($blob) {
    // Check for a JPG header.
    if ($blob[0] == chr(0xFF) && $blob[1] == chr(0xD8)) {
      return TRUE;
    }

    // Check for a MP4 header.
    if ($blob[0] == chr(0x00) && $blob[1] == chr(0x00)) {
      return TRUE;
    }

    return FALSE;
  }


  /**
   * Runs a POST request against the API.
   *
   * Snapchat appears to only use POST for API requests, so this is really
   * the only function used to query the API.
   *
   * @param $endpoint The address of the resource being requested (e.g. '/update_snaps' or '/friend').
   * @param $data An associative array of values to send to the API. A request token is added automatically.
   * @param $params An array containing the parameters used to generate the request token.
   * @param $multipart (optional) If TRUE, sends the request as multipart/form-data. Defaults to FALSE.
   * @return The data returned from the API (decoded if JSON). Returns FALSE if the request failed.
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

    // TODO: replace with a check for application/octet-stream
    if ($endpoint == '/blob') {
      return $result;
    }

    // Add support for foreign characters in the JSON response.
    $result = iconv('UTF-8', 'UTF-8//IGNORE', utf8_encode($result));

    $data = json_decode($result);
    return json_last_error() == JSON_ERROR_NONE ? $data : FALSE;
  }


  /**
   * Handles login.
   *
   * @param $username The username for the Snapchat account.
   * @param $password The password associated with the username.
   * @return The data returned by the service. Generally, returns the same result as calling self::getUpdates().
   */
  public function login($username, $password) {
    $timestamp = self::timestamp();
    $result = self::post(
      '/login',
      array(
        'username' => $username,
        'password' => $password,
        'timestamp' => $timestamp,
      ),
      array(
        self::STATIC_TOKEN,
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
   * @return TRUE if successful, FALSE otherwise.
   */
  public function logout() {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
   * Creates a user account.
   *
   * @param $username The desired username.
   * @param $password The password to associate with the account.
   * @param $email The email address to associate with the account.
   * @param $birthday The user's birthday (yyyy-mm-dd).
   * @return The data returned by the service. Generally, returns the same result as calling self::getUpdates().
   */
  public function register($username, $password, $email, $birthday) {
    $timestamp = self::timestamp();
    $result = self::post(
      '/register',
      array(
        'birthday' => $birthday,
        'password' => $password,
        'email' => $email,
        'timestamp' => $timestamp,
      ),
      array(
        self::STATIC_TOKEN,
        $timestamp,
      )
    );

    if (!isset($result->token)) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
      '/registeru',
      array(
        'email' => $email,
        'username' => $username,
        'timestamp' => $timestamp,
      ),
      array(
        self::STATIC_TOKEN,
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
   * Retrieves general user, friend, and snap updates.
   *
   * @return The data returned by the service or FALSE on failure.
   */
  public function getUpdates() {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
      return $result->updates_response;
    }

     return $result;
  }


  /**
   * Gets the user's snaps.
   *
   * @return An array of snaps or FALSE on failure.
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
   * Queries the friend-finding service.
   *
   * @param $numbers An array of phone numbers.
   * @param $country The country code. Defaults to US.
   * @return An array of user objects.
   */
  public function findFriends($numbers, $country = 'US') {
    $numbers = array_flip($numbers);

    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
      '/find_friends',
      array(
        'countryCode' => $country,
        'numbers' => json_encode($numbers),
        'timestamp' => $timestamp,
        'username' => $this->username,
      ),
      array(
        $this->auth_token,
        $timestamp,
      )
    );

    if (isset($result->results)) {
      return $result->results;
    }

    return $result;
  }


  /**
   * Gets the user's friends.
   *
   * @return An array of friends or FALSE on failure.
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
   * @return An array of friends or FALSE on failure.
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
   * @param $username The username of the friend to add.
   * @return TRUE if successful, FALSE otherwise.
   */
  public function addFriend($username) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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

    if (strpos($result->message, 'Sorry! Couldn\'t find') === 0) {
      return FALSE;
    }

    return !empty($result->message);
  }


  /**
   * Adds multiple friends.
   *
   * @param $usernames An array of usernames to add as friends.
   * @return TRUE if successful, FALSE otherwise.
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

    $timestamp = self::timestamp();
    $result = self::post(
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
   * @param $username The username of the friend to delete.
   * @return TRUE if successful, FALSE otherwise.
   */
  public function deleteFriend($username) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
   * Sets a friend's display name.
   *
   * @param $username The username of the user to modify.
   * @param $display The display name.
   * @return TRUE if successful, FALSE otherwise.
   */
  public function setDisplayName($username, $display) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
   * @param $username The username to be blocked.
   * @return TRUE if successful, FALSE otherwise.
   */
  public function block($username) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
   * @param $username The username to be unblocked.
   * @return TRUE if successful, FALSE otherwise.
   */
  public function unblock($username) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
   * @param $id The snap ID.
   * @return The snap data or FALSE on failure.
   */
  public function getMedia($id) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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

    if (self::isMedia(substr($result, 0, 2))) {
      return $result;
    }
    else {
      $result = self::decrypt($result);

      if (self::isMedia(substr($result, 0, 2))) {
        return $result;
      }
    }

    return FALSE;
  }


  /**
   * Sends event information to Snapchat.
   *
   * @param $events An array of events to send to Snapchat (generally usage data).
   * @param $snap_info (optional) Data to send along in addition to the event array. This is used by the app to mark snaps as viewed. Defaults to an empty object.
   * @return TRUE on success, FALSE on failure.
   */
  public function sendEvents($events, $snap_info = array()) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
   * @param $id The snap to mark as viewed.
   * @param $time The amount of time (in seconds) the snap was viewed. Defaults to 1.
   * @return TRUE on success, FALSE on failure.
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
   * @param $id The snap to mark as shot.
   * @param $time The amount of time (in seconds) the snap was viewed. Defaults to 1.
   * @return TRUE on success, FALSE on failure.
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
   * Uploads a file.
   *
   * @param $type The media type, i.e. MEDIA_IMAGE or MEDIA_VIDEO.
   * @param $data The file data to upload.
   * @return The media ID or FALSE on failure.
   */
  public function upload($type, $data) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    // To make cURL happy, we write the data to a file first.
    $temp = tempnam(sys_get_temp_dir(), 'Snap');
    file_put_contents($temp, self::encrypt($data));

    if (version_compare(PHP_VERSION, '5.5.0', '>=')) {
      $cfile = curl_file_create($temp, ($type == Snapchat::MEDIA_IMAGE ? 'image/jpeg' : 'video/quicktime') ,'test_name');
    }

    // TODO: Media IDs are GUIDs now.
    $media_id = strtoupper($this->username) . '~' . time();
    $timestamp = self::timestamp();
    $result = self::post(
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

    return is_null($result) ? $media_id : FALSE;
  }


  /**
   * Sends a snap.
   *
   * @param $media_id The media ID of the snap to send.
   * @param $recipients An array of recipients.
   * @param $time (optional) The time in seconds the snap should be available (1-10). Defaults to 3.
   * @return TRUE on success or FALSE on failure.
   */
  public function send($media_id, $recipients, $time = 3) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
   * @param $friends An array of usernames of the friends for which to retrieve best friend information.
   * @return An associative array keyed by username or FALSE on failure.
   */
  public function getBests($friends) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
   * @return TRUE on success or FALSE on failure.
   */
  public function clearFeed() {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
   * @param $setting The privacy setting, i.e. PRIVACY_EVERYONE or PRIVACY_FRIENDS.
   * @return TRUE on success or FALSE on failure.
   */
  public function updatePrivacy($setting) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
   * @param $email The new email address.
   * @return TRUE on success or FALSE on failure.
   */
  public function updateEmail($email) {
    // Make sure we're logged in and have a valid access token.
    if (!$this->auth_token || !$this->username) {
      return FALSE;
    }

    $timestamp = self::timestamp();
    $result = self::post(
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
