<?php
require_once('../BrachioMailer.php');

$currentpath = str_replace('\\', '/', realpath('.')) . '/';
$messagehtml = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
 "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=UTF-8" />
		<title>Test4</title> 
	</head>
	<body>
		<h1>test message4</h1>
		<p>This is the <i>fourth</i> <b>test message</b>.<br />
		With use of HTML (with plain text fall-back) and a text file attachment.</p>
	</body>
</html>
';

$brachioMailer = new BrachioMailer(true);
$brachioMailer->setMessagecontenttype('text/html');
$brachioMailer->setUsesmime(true); // Turn it on.
$brachioMailer->setSmimecachefolder($currentpath . 'smime/'); // Here a copy of a signed and unsigned message will be stored.
$brachioMailer->setSmimekeypublic($currentpath . 'smime/tester@localhost.local.crt'); // Do not store this in a web accessable folder.
$brachioMailer->setSmimekeyprivate($currentpath . 'smime/tester@localhost.local.key'); // Do not store this in a web accessable folder.
$brachioMailer->setSmimekeyprivatepassphrase('testtest'); // Do use this for additional security.
$brachioMailer->setSmimeextracerts($currentpath . 'smime/testCA.crt');
if (!$brachioMailer->Send('tester2@localhost.local', 'tester@localhost.local', 'test6', $messagehtml)) {
	echo 'Error<br />';
}
