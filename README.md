# AutoSieve #

Crawl imap inbox and create sieve rules, e.g. for each "from"-address file mails into a own mailbox

### License ###

This program is free software; see LICENSE for more details.

### Details ###

AutoSieve provides currently only one kind of automated rule creation: For each "From"-address
{name} < {localpart}@{domain}.{tld} > found in a mail in the imap INBOX 

* a subfolder INBOX.{domain}.{name} will be created and subscribed
* a sieve rule will be attached to a script which will move further mails from the same sender in this folder
* and the mail will be moved into this subfolder

Implementing this functionality in an external script was necessary since sieve does not provide the following
features:

* Subscribe to a newly created folder
* String replacement within the address, especially removing dots (.)

### Setup ###

You can use the script in the ```example``` folder as standalone, or you can use the ```AutoSieve``` class within
your project. Use composer to add this repository to your dependencies:

```JSON
{
        "require": {
                "elearning-ag/autosieve": "dev-master"
        }
}
```


### Examples ###

The following example demonstrates how to use AutoSieve:

```php
$imap = ['host' => 'localhost', 'port' => 993, 'user' => 'test', 'password' => 's3cr37!!'];
$autosieve = ELearningAG\AutoSieve\AutoSieve::getInstance($imap)->addSenderMailboxes()->save();
```

### Usage ###

First of all you need to create and configure an instance of ```AutoSieve```:

```php
$imap = ['host' => 'localhost', 'port' => 993, 'user' => 'test', 'password' => 's3cr37!!'];
$sieve = ['host' => 'localhost', 'port' => 4190, 'user' => 'test', 'password' => 's3cr37!!'];
$autosieve = ELearningAG\AutoSieve\AutoSieve::getInstance($imap, $sieve);
```

If you do not provide a configuration for sieve, ```AutoSieve``` will try to use the imap credentials and port 4190.

Currently there is only one "magic" function, which creates a folder for each yet unknown sender:

```php
$autosieve->addSenderMailboxes();
```

Finally you have to save your rules and create new imap mailboxes:

```php
$autosieve->save();
```

If you need anything different, you can use the following three methods, to write your own automatisations:

* ```addRule($rule)``` - add a sieve rule. The method expects an array of strings, where each entry is one row in the sieve script
* ```addMailbox($mailbox)``` - add an imap mailbox.
* ```addMessageToMailbox($message, $mailbox) - move the $message from the INBOX to the $mailbox.

All changes are not applied until you call ```save()```!


### Contact ###

For any questions you can contact Severin Neumann <s.neumann@elearning-ag.de>
