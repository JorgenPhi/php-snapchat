<?php

class PHPSnapchatTest extends PHPUnit_Framework_TestCase {

  const STRANGE_USERNAME = 'superstrangename1234!!ö';

  private $users = array();

  /**
   * Run before each test is started.
   */
  public function setUp() {
    $this->users = array(
      1 => array(
        'name' => 'u1php5' . PHP_MINOR_VERSION,
        'pass' => '123456789',
      ),
      2 => array(
        'name'=> 'u2php5' . PHP_MINOR_VERSION,
        'pass' => '123456789',
      ),
      3 => array(
        'name'=> 'u3php5' . PHP_MINOR_VERSION,
        'pass' => '123456789',
      ),
      4 => array(
        'name'=> 'u4php5' . PHP_MINOR_VERSION,
        'pass' => '123456789',
      ),
      5 => array(
        'name'=> 'u5php5' . PHP_MINOR_VERSION,
        'pass' => '123456789',
      ),
    );
  }

  /**
   * Run after each test is completed.
   */
  public function tearDown() {

  }

  public function testWrongLoginUser1() {
    $snapchat = new Snapchat($this->users[1]['name'], 'fail');
    $this->assertFalse($snapchat->auth_token);
  }

  // public function testLoginUser1() {
  //  $snapchat = new Snapchat($this->users[1]['name'], $this->users[1]['pass']);
  //  $this->assertNotEquals($snapchat->auth_token, FALSE);
  // }

  public function testWrongLoginUser2() {
    $snapchat = new Snapchat($this->users[2]['name'], 'fail');
    $this->assertFalse($snapchat->auth_token);
  }

  // public function testLoginUser2() {
  //  $snapchat = new Snapchat($this->users[2]['name'], $this->users[2]['pass']);
  //  $this->assertNotEquals($snapchat->auth_token, FALSE);
  // }

  public function testSendAndReceivePicture() {
    $this->_sendAndReceive(implode(DIRECTORY_SEPARATOR, array(__DIR__, 'media', 'picture.jpg')), Snapchat::MEDIA_IMAGE);
  }

  public function testSendAndReceiveMovie() {
    $this->_sendAndReceive(implode(DIRECTORY_SEPARATOR, array(__DIR__, 'media', 'movie.mov')), Snapchat::MEDIA_VIDEO);
  }

  private function _sendAndReceive($file, $type) {
    $snapchat = new Snapchat($this->users[1]['name'], $this->users[1]['pass']);
    $this->assertNotEquals($snapchat->auth_token, FALSE, 'Login failed for test user 1.');

    $id = $snapchat->upload($type, file_get_contents($file));
    $this->assertEquals(is_string($id), TRUE, ($type == Snapchat::MEDIA_IMAGE ? 'Image' : 'Video') . ' upload failed.');

    $result = $snapchat->send($id, array($this->users[2]['name']));
    $this->assertEquals($result, TRUE, 'Media send failed.'); // TODO

    $this->assertEquals($snapchat->clearFeed(), TRUE, 'Failed to clear the feed of test user 1.');
    $this->assertEquals($snapchat->logout(), TRUE, 'Logout failed for test user 1.');

    $snapchat = new Snapchat($this->users[2]['name'], $this->users[2]['pass']);
    $this->assertNotEquals($snapchat->auth_token, FALSE, 'Login failed for test user 2.');

    $snaps = $snapchat->getSnaps();
    $this->assertNotEquals($snaps, FALSE, 'Failed to get snap list.');

    foreach($snaps as $snap) {
      if ($snap->status == Snapchat::STATUS_DELIVERED && strcmp($snap->recipient, $snapchat->username) == 0) {
        $data = $snapchat->getMedia($snap->id);
        $this->assertEquals($snapchat->markSnapViewed($snap->id), TRUE, 'User 2 marked snap viewed.');
        $this->assertEquals($snapchat->markSnapShot($snap->id), TRUE, 'User 2 marked screenshot.');
        $this->assertEquals(is_string($data), TRUE);
      }
    }

    $this->assertEquals($snapchat->clearFeed(), TRUE, 'Failed to clear the feed of test user 2.');
    $this->assertEquals($snapchat->logout(), TRUE, 'Logout failed for test user 2.');
  }

  public function testWrongMedia() {
    $snapchat = new Snapchat($this->users[1]['name'], $this->users[1]['pass']);
    $this->assertNotEquals($snapchat->auth_token, FALSE, 'Login failed for test user 1.');
    $data = $snapchat->getMedia('12345');
    $this->assertEquals($data, FALSE);
  }

  public function testManageFriends() {
    $snapchat = new Snapchat($this->users[1]['name'], $this->users[1]['pass']);
    $this->assertNotEquals($snapchat->auth_token, FALSE, 'Login failed for test user 1.');

    $this->assertEquals($snapchat->addFriend(PHPSnapchatTest::STRANGE_USERNAME), FALSE, 'User 1 added a strange username.');
    $this->assertEquals($snapchat->deleteFriend($this->users[3]['name']), TRUE, 'User 1 deleted an unknown friend.');

    $this->assertEquals($snapchat->addFriend($this->users[3]['name']), TRUE, 'User 1 added user 3 as friend.');
    $this->assertEquals($snapchat->deleteFriend($this->users[3]['name']), TRUE, 'User 1 removed user 3 from friends.');
    $this->assertEquals($snapchat->addFriends(array($this->users[4]['name'], $this->users[5]['name'])), TRUE, 'User 1 added multiple friends.');

    $this->assertEquals($snapchat->deleteFriend($this->users[4]['name']), TRUE, 'User 1 removed user 4 from friends.');
    $this->assertEquals($snapchat->deleteFriend($this->users[5]['name']), TRUE, 'User 1 removed user 5 from friends.');

    $friends = $snapchat->getFriends();
    $this->assertEquals(count($friends) > 0, TRUE);
    $friends = $snapchat->getAddedFriends();
    $this->assertEquals(count($friends) > 0, TRUE);
    $bestFriends = $snapchat->getBests(array($this->users[2]['name']));
    $this->assertEquals(is_int($bestFriends[$this->users[2]['name']]['score']), TRUE);
  }

  public function testManageUserSettings() {
    $snapchat = new Snapchat($this->users[1]['name'], $this->users[1]['pass']);
    $this->assertNotEquals($snapchat->auth_token, FALSE, 'Login failed for test user 1.');

    $this->assertEquals($snapchat->block($this->users[5]['name']), TRUE, 'User 1 blocked user 5.');
    $this->assertEquals($snapchat->unblock($this->users[5]['name']), TRUE, 'User 1 unblocked user 5.');

    $this->assertEquals($snapchat->updatePrivacy(Snapchat::PRIVACY_EVERYONE), TRUE, 'User 1 accepts snaps from everyone.');
    $this->assertEquals($snapchat->updatePrivacy(Snapchat::PRIVACY_FRIENDS), TRUE, 'User 1 accepts snaps only from friends.');

    $this->assertEquals($snapchat->updateEmail($this->users[1]['name'] . '@php-snapchat.tld'), FALSE, 'User 1 attempted to change his email to an invalid address.');
    $this->assertEquals($snapchat->updateEmail($this->users[1]['name'] . '@php-snapchat.org'), TRUE, 'User 1 changed his email.');

    $this->assertEquals($snapchat->setDisplayName($this->users[2]['name'], $this->users[2]['name']), TRUE, 'User 1 set user 2\'s display name.');
  }
}
