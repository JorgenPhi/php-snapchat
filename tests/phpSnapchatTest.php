<?php

class PHPSnapchatTest extends PHPUnit_Framework_TestCase {

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
        //$this->assertEquals(is_string($data),TRUE);
        //file_put_contents(substr($file,0,-5)."2".substr($file,-4), $data);
        $this->assertEquals($snapchat->markSnapViewed($snap->id), TRUE, 'User 2 marked snap viewed.');
      }
    }

    $this->assertEquals($snapchat->clearFeed(), TRUE, 'Failed to clear the feed of test user 2.');
    $this->assertEquals($snapchat->logout(), TRUE, 'Logout failed for test user 2.');
  }
}
