<?php
require_once('../BrachioMailer.php');

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
		With use of HTML (with plaintext fallback) and a textfile attachment.</p>
	</body>
</html>
';

$brachioMailer = new BrachioMailer(true);
$brachioMailer->setMessagecontenttype('text/html');
$brachioMailer->addAttachment('attachment.txt', 'test4_document.txt', 'text/plain', 'Test text file.');
if (!$brachioMailer->Send('tester2@localhost.local', 'tester@localhost.local', 'test4', $messagehtml)) {
	echo 'Error<br />';
}
