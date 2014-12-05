Snapchat for PHP
================
[![Build Status](https://travis-ci.org/JorgenPhi/php-snapchat.png)](https://travis-ci.org/JorgenPhi/php-snapchat)

This library is built to communicate with the Snapchat API. It is nearly
feature complete but still lacks some functionality available in the latest
versions of the official apps (namely Stories).

It's similar to the [excellent Snaphax library](http://github.com/tlack/snaphax)
built by Thomas Lackner <[@tlack](http://twitter.com/tlack)>, but the approach
is different enough that I figured it deserved its own repo.


Usage
-----

Include src/snapchat.php via require_once or Composer or whatever, then:

```php
<?php

// Log in:
$snapchat = new Snapchat('username', 'password');

// Get your feed:
$snaps = $snapchat->getSnaps();

// Get your friends' stories:
$snaps = $snapchat->getFriendStories();

// Download a specific snap:
$data = $snapchat->getMedia('122FAST2FURIOUS334r');
file_put_contents('/home/dan/snap.jpg', $data);

// Download a specific story:
$data = $snapchat->getStory('[story_media_id]', '[story_key]', '[story_iv]');

// Download a specific story's thumbnail:
$data = $snapchat->getStoryThumb('[story_media_id]', '[story_key]', '[thumbnail_iv]');

// Mark the snap as viewed:
$snapchat->markSnapViewed('122FAST2FURIOUS334r');

// Mark the story as viewed:
$snapchat->markStoryViewed('[story_id]');

// Screenshot!
$snapchat->markSnapShot('122FAST2FURIOUS334r');

// Upload a snap and send it to me for 8 seconds:
$id = $snapchat->upload(
	Snapchat::MEDIA_IMAGE,
	file_get_contents('/home/dan/whatever.jpg')
);
$snapchat->send($id, array('stelljes'), 8);

// Upload a video story:
$id = $snapchat->upload(
	Snapchat::MEDIA_VIDEO,
	file_get_contents('/home/dan/whatever.mov')
);
$snapchat->setStory($id, Snapchat::MEDIA_VIDEO);

// Destroy the evidence:
$snapchat->clearFeed();

// Find friends by phone number:
$friends = $snapchat->findFriends(array('18006492568', '7183876962'));

// Get a list of your friends:
$friends = $snapchat->getFriends();

// Add some people as friends:
$snapchat->addFriends(array('bill', 'bob'));

// Add someone you forgot:
$snapchat->addFriend('bart');

// Get a list of the people you've added:
$added = $snapchat->getAddedFriends();

// Find out who Bill and Bob snap the most:
$bests = $snapchat->getBests(array('bill', 'bob'));

// Set Bart's display name:
$snapchat->setDisplayName('bart', 'Barty');

// Block Bart:
$snapchat->block('bart');

// Unblock Bart:
$snapchat->unblock('bart');

// Delete Bart entirely:
$snapchat->deleteFriend('bart');

// You only want your friends to be able to snap you:
$snapchat->updatePrivacy(Snapchat::PRIVACY_FRIENDS);

// You want to change your email:
$snapchat->updateEmail('dan@example.com');

// Log out:
$snapchat->logout();

?>
```

##Snaptcha

Below is an example on how to bypass the "Snaptcha" made by Snapchat.
This fork includes two new methods.

getCaptcha()

and

sendCaptcha()

[The new endpoints are discussed in more detail here](http://www.hakobaito.co.uk/b/bypassing-snaptcha)

Example:

```php
<?php

/*
Snaptcha Bypass sample
hako 2014
*/

include 'src/snapchat.php';

// dummy variables.
$email = "snaptchabypassexample@gmail.com";
$password = "snaptchabypassexamplepass";
$birthday = "1933-05-13";
$username = "snaptchauser";

$s = new Snapchat();
$s->register($email,$password,$birthday); // Register an account...
$registration = $s->register_username($email, $username); // Register desired username...

// registration check...
if(is_int($registration)) {

	if ($registration == 69) {
		print "username is too short!\n";
		exit();
	}

	else if ($registration == 70) {
		print "username is too long!\n";
		exit();
	}

	else if ($registration == 71) {
		print "bad username\n";
		exit();
	}

	else if ($registration == 72) {
		print "username is taken!\n";
		exit();
	}
}

$captcha_id = $s->getCaptcha($username, true);	// verify yourself...

//   Ask the user for the captcha,
//  (should be replaced with respected ghost images)...
//  returns false if unable to get the captcha_id.

print $captcha_id . "\n";
echo "captcha: ";
$solution_raw = fgets(STDIN); // Solution is 9 characters long eg. 001010011
$solution = str_replace("\n", "", $solution_raw); // strip off invisible characters.
$verify = $s->sendCaptcha($solution, $captcha_id, $username); // Send off Snaptcha.

// Check if Snaptcha is correct...
if($verify == TRUE) {
    print "Snaptcha passed, Snapchat account verified.";
}
else if($verify == FALSE) {
    print "Incorrect Snaptcha, Snapchat account not verified.";
}

?>
```


Documentation
------------

There is none, but I tried to mark up the code well enough to make up for it.
Error handling is pretty weak, so watch for that.


License
------------

MIT
