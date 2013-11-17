<?php

class PHPSnapchatPrepareTest extends PHPUnit_Framework_TestCase {

	public function testCreateUser() {
		$snapchat = new Snapchat();
		$snapchat->register('u5php53','123456789','u5php53@oskarholowaty.com','1987-01-03');

		$snapchat->register('u5php54','123456789','u5php54@oskarholowaty.com','1987-01-03');

		$snapchat->register('u5php55','123456789','u5php55@oskarholowaty.com','1987-01-03');
	}
}