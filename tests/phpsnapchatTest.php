<?php

class PHPSnapchatTest extends PHPUnit_Framework_TestCase
{
	static $fixture_file = 'test/credentials.yml';
	private $testUser1 = '';
	private $testPass1 = '';

	private $testUser2 = '';
	private $testPass2 = '';

	/**
	 * Run before each test is started.
	 */
	public function setUp() {
		$this->testUser1 = "u1php5".PHP_MINOR_VERSION;
		$this->testUser2 = "u2php5".PHP_MINOR_VERSION;
		$this->testPass1 = '123456789';
		$this->testPass2 = '123456789';
	}

	/**
	 * Run after each test is completed.
	 */
	public function tearDown()
	{

	}

	public function testWrongLoginUser1() {
		$snapchat = new Snapchat($this->testUser1, "fail");
		$this->assertFalse($snapchat->auth_token);
	}

	// public function testLoginUser1() {
	// 	$snapchat = new Snapchat($this->testUser1, $this->testPass1);
	// 	$this->assertNotEquals($snapchat->auth_token,false);
	// }

	public function testWrongLoginUser2() {
		$snapchat = new Snapchat($this->testUser2, "fail");
		$this->assertFalse($snapchat->auth_token);
	}

	// public function testLoginUser2() {
	// 	$snapchat = new Snapchat($this->testUser2, $this->testPass2);
	// 	$this->assertNotEquals($snapchat->auth_token,false);
	// }

	public function testSendandReceivePicture() {
		$this->_sendAndReceive(__DIR__.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'picture1.jpg',Snapchat::MEDIA_IMAGE);
	}

	public function testSendandReceiveMovie() {
		$this->_sendAndReceive(__DIR__.DIRECTORY_SEPARATOR.'media'.DIRECTORY_SEPARATOR.'movie1.mov',Snapchat::MEDIA_VIDEO);
	}

	private function _sendAndReceive($file,$type) {
		$snapchat = new Snapchat($this->testUser1, $this->testPass1);
		$this->assertNotEquals($snapchat->auth_token,FALSE,'User1 login failed.');

		$id = $snapchat->upload(
			$type,
			file_get_contents($file)
		);

		$this->assertEquals(is_string($id),TRUE,($type == Snapchat::MEDIA_IMAGE ? 'image' : 'video') . ' upload failed');

		$result = $snapchat->send($id, array($this->testUser2));
		$this->assertEquals($result,TRUE,'Media send failed.'); // TODO
		$this->assertEquals($snapchat->clearFeed(),TRUE,'User1 clearFeed failed.');
		$this->assertEquals($snapchat->logout(),TRUE,'User1 logout failed.');

		$snapchat = new Snapchat($this->testUser2, $this->testPass2);
		$this->assertNotEquals($snapchat->auth_token,FALSE,'User2 login failed.');

		foreach($snapchat->getSnaps() as $snap) {
			if ($snap->status == Snapchat::STATUS_DELIVERED &&
				 strcmp($snap->recipient,$snapchat->username) == 0) {
				$data = $snapchat->getMedia($snap->id);
				//$this->assertEquals(is_string($data),TRUE);
				//file_put_contents(substr($file,0,-5)."2".substr($file,-4), $data);
				$this->assertEquals($snapchat->markSnapViewed($snap->id),TRUE,'User2 snap mark as viewed.');
			}
		}
		$this->assertEquals($snapchat->clearFeed(),TRUE,'User 2 clearFeed failed.');
		$this->assertEquals($snapchat->logout(),TRUE,'User2 logout failed.');
	}
}