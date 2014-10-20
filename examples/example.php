<?php
require_once('../vendor/autoload.php');
require_once('./config.php');

$autosieve = ELearningAG\AutoSieve\AutoSieve::getInstance($config['imap'])->addSenderMailboxes()->save();
