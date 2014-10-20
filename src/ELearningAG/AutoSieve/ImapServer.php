<?php
namespace ELearningAG\AutoSieve;

class ImapServer extends \Fetch\Server implements Interfaces\IMAPHandler {
	public function subscribeToMailBox($mailbox) {
		return imap_subscribe($this->getImapStream(), $this->getServerSpecification() . $mailbox);
	}
};
