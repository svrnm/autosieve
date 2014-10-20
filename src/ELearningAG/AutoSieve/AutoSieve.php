<?php
namespace ELearningAG\AutoSieve;

use ELearningAG\AutoSieve\Interfaces\IMAPHandler;
use ELearningAG\AutoSieve\Interfaces\SieveHandler;

class AutoSieve {
	protected $imap;
	protected $sieve;
	protected $scriptName = 'autosieve';
	protected $sievePlugins = ['fileinto','variables','mailbox','envelope'];
	protected $sieveRuleName = 'Autosieve-Do-Not-Touch';
	protected $sieveRuleBegin = 'Autosieve-Begin';
	protected $sieveHeader = '';
	protected $rules = [];
	protected $mailboxes = [];
	protected $messages = [];

	public function __construct(IMAPHandler $imap, SieveHandler $sieve) {
		$this->imap = $imap;
		$this->sieve = $sieve;
	}

	public static function getInstance($imapConfig, $sieveConfig = []) {
			$imap = new \ELearningAG\AutoSieve\ImapServer($imapConfig['host'], isset($imapConfig['port']) ? $imapConfig['port'] : 993);
			if(isset($imapConfig['user'])) {
					$imap->setAuthentication($imapConfig['user'], $imapConfig['password']);
			}
			if(empty($sieveConfig)) {
				$sieveConfig = $imapConfig;
				$sieveConfig['port'] = 4190;
			}
			$sieve = new \ELearningAG\AutoSieve\SieveServer;
			$sieve->connect($sieveConfig['host'], $sieveConfig['port']);
			$sieve->login($sieveConfig['user'], $sieveConfig['password']);

			$instance = new static($imap, $sieve);

			return $instance;
	}

	public function setScriptName($name) {
		$this->scriptName = $name;
		return $this;
	}

	public function getScriptName() {
		return $this->scriptName;	
	}

	public function buildScript() {
		if(empty($this->sieveHeader)) {
			$script = [];
			$script[] = 'require ["'.implode('","',$this->sievePlugins).'"];';
			if(!empty($this->sieveRuleName)) {
				$script[] = '# rule:['.$this->sieveRuleName.']';
			}
			$script[] = 'if true {';
		} else {
			$script = $this->sieveHeader;
		}
		$script[] = implode(PHP_EOL, array_map(function($rule) {
			return implode(PHP_EOL, $rule);
		}, $this->rules));
		$script[] = '}';
		return implode(PHP_EOL, $script);
	}


	protected function saveSieve() {
		$scripts = $this->sieve->listScripts();
		if(in_array($this->scriptName, $scripts)) {
				$old = explode(PHP_EOL, $this->sieve->getScript($this->scriptName));
				array_pop($old);		
				array_pop($old);		
				$this->sieveHeader = $old;
		}
		$e = $this->sieve->installScript($this->scriptName, $this->buildScript(), true);
		if($e !== true) {
			throw new Exception($e->message);
		}
	}

	protected function saveImap() {
			foreach($this->mailboxes as $mailbox) {
			if(!$this->imap->hasMailBox($mailbox)) {
					$create = $this->imap->createMailBox($mailbox); 
					if(!$create) {
						throw new Exception('Could not create mailbox');
					}
					$subscribe = $this->imap->subscribeToMailBox($mailbox);
					if(!$subscribe) {
						throw new Exception('Could not subscribe to mailbox');
					}
			}
			foreach($this->messages[$mailbox] as $message) {
				$message->moveToMailbox($mailbox);
			}
		}

	}

	public function save() {
		$this->saveSieve();
		$this->saveImap();
		return $this;
	}

	public function addressToMailbox($address) {
		$name = $address['name'];
		list($localpart,$domain) = explode('@', $address['address']);
		if(empty($name)) {
			$name = str_replace('.','-',$localpart);
		}
		// Strip TLD
		$dot = strrpos($domain,'.');
		if($dot !== false) {
			$domain = substr($domain,0,$dot);
		}
		$domain = ucfirst(str_replace('.','-', $domain));
		$mailbox = 'INBOX.'.$domain.'.'.$name;
		return $mailbox;
	}

	public function addRule($rule) {
		$this->rules[] = $rule;
		return $this;
	}

	public function addMailbox($mailbox) {
		$this->mailboxes[] = $mailbox;
		return $this;
	}

	public function getMailboxes() {
		return $this->mailboxes;
	}

	public function addMessageToMailbox($message, $mailbox) {
		$this->messages[$mailbox][] = $message;
	}

	public function addSenderMailboxes() {
		foreach ($this->imap->getMessages() as $message) {
			$from = $message->getAddresses('from');
			
			$mailbox = $this->addressToMailbox($from);
		
			list($localpart,$domain) = explode('@', $from['address']);
			$rule = [
				'if envelope :matches :domain "From" "'.$domain.'" {',
				'  if envelope :matches :localpart "From" "'.$localpart.'" {',
				'    fileinto "'.$mailbox.'";',
				'  }',
				'}'
			];
			$this->addRule($rule);
			$this->addMailbox($mailbox);
			$this->addMessageToMailbox($message, $mailbox);
		}
		return $this;
	}
}
