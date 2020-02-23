<?php
require_once('../BrachioMailer.php');

$messagetext = 'Hi

This is the first plaintext test message.

Greeting,
Tester1';

$brachioMailer = new BrachioMailer(true);
$brachioMailer->setOrganization('Testers N.V.');
$brachioMailer->setReplyto('tester3@localhost.local');
$brachioMailer->setImportance('low');
if (!$brachioMailer->Send('tester2@localhost.local', 'tester@localhost.local', 'test1', $messagetext)) {
	echo 'Error<br />';
}

