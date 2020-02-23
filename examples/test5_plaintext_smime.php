<?php
require_once('../BrachioMailer.php');

$currentpath = str_replace('\\', '/', realpath('.')) . '/';
$messagetext = 'hi,

This is a S/MIME signed plaintext test message5.
(The S/MIME certificate is and cannot be signed by trusted CA.)

Greeting,
sender';

$brachioMailer = new BrachioMailer(true);
$brachioMailer->setMessagecontenttype('text/plain');
$brachioMailer->setUsesmime(true); // Turn it on.
$brachioMailer->setSmimecachefolder($currentpath . 'smime/'); // Here a copy of a signed and unsigned message will be stored.
$brachioMailer->setSmimekeypublic($currentpath . 'smime/tester@localhost.local.crt'); // Do not store this in a web accessable folder.
$brachioMailer->setSmimekeyprivate($currentpath . 'smime/tester@localhost.local.key'); // Do not store this in a web accessable folder.
$brachioMailer->setSmimekeyprivatepassphrase('testtest'); // Do use this for additional security.
$brachioMailer->setSmimeextracerts($currentpath . 'smime/testCA.crt');
if (!$brachioMailer->Send('tester2@localhost.local', 'tester@localhost.local', 'test5', $messagetext)) {
	echo 'Error<br />';
}
