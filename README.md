Snapchat for PHP
============

This library is built to communicate with the Snapchat API. So far it supports
logging in/out, fetching snaps, downloading snaps, marking snaps viewed,
uploading snaps, and sending snaps.

It's similar to the [excellent Snaphax library](http://github.com/tlack/snaphax)
built by Thomas Lackner <[@tlack](http://twitter.com/tlack)>, but the approach
is different enough that I figured it deserved its own repo.

Eventually, it'll have better friend support and maybe support user creation
and device pairing.


Usage
------------

Include src/snapchat.php via require_once or Composer or whatever, then:

```
$snapchat = new Snapchat();

// Log in:
$snapchat->login('username', 'password');

// Get your feed:
$snaps = $snapchat->getSnaps();

// Download a specific snap:
$data = getMedia('122FAST2FURIOUS334r');
file_put_contents('/home/dan/snap.jpg', $data);

// Mark the snap as viewed:
$snapchat->markSnapViewed('122FAST2FURIOUS334r');

// Upload a snap and send it to me for 8 seconds:
$id = $snapchat->upload(
	Snapchat::MEDIA_IMAGE,
	file_get_contents('/home/dan/whatever.jpg')
);
$snapchat->send($id, array('stelljes'), 8);

// Log out:
$snapchat->logout();
```


Documentation
------------

There is none, but I tried to mark up the code well enough to make up for it.
Error handling is pretty weak, so watch for that.


License
------------

MIT