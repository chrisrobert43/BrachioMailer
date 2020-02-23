<?php
require_once('../BrachioMailer.php');

$messagetext = 'Hi,

This is plaintext test message3 with a image attachment.

Greetings,
Sender';

$brachioMailer = new BrachioMailer(true);
$brachioMailer->addAttachment('attachment.png', 'test3_image.png', 'image/png', 'Test image file.');
if (!$brachioMailer->Send('tester2@localhost.local', 'tester@localhost.local', 'test3', $messagetext)) {
	echo 'Error<br />';
}

