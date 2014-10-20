<?php
namespace ELearningAG\AutoSieve;

use ELearningAG\AutoSieve\Interfaces\IMAPHandler;
use ELearningAG\AutoSieve\Interfaces\SieveHandler;

class AutoSieve {
	protected $imap;
	protected $sieve;
	protected $isVerbose = false;
	protected $scriptName = 'autosieve';
	protected $sievePlugins = ['fileinto','variables','mailbox','envelope'];
	protected $sieveRuleName = 'Autosieve-Do-Not-Touch';
	protected $rules = [];
	protected $mailboxes = [];

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
			$sieve = new \Net_Sieve;
			$sieve->connect($sieveConfig['host'], $sieveConfig['port']);
			$sieve->login($sieveConfig['user'], $sieveConfig['password']);

			$instance = new static($imap, $sieve);

			return $instance;
	}

	public function verbose($setVerbose = true) {
		$this->isVerbose = $setVerbose;
		return $this;
	}

	public function setScriptName($name) {
		$this->scriptName = $name;
		return $this;
	}

	public function buildScript() {
		$script = [];
		$script[] = 'require ["'.implode('","',$this->sievePlugins).'"];';
		if(!empty($this->sieveRuleName)) {
			$script[] = '# rule:['.$this->sieveRuleName.']';
		}
		$script[] = 'if true {';
		$script[] = implode(PHP_EOL, array_map(function($rule) {
			return implode(PHP_EOL, $rule);
		}, $this->rules));
		$script[] = '}';
		return implode(PHP_EOL, $script);
	}


	public function save() {
		//$scripts = $this->sieve->listScripts();
		//if(!in_array($this->scriptName, $scripts)) {
		//$e = $sieve->installScript($this->scriptName, $this->buildScript(), true);
		//}
		//if($e !== true) {
		//	throw $e;
		//}
			//return $this;
			//
						/*
			* Move to Save
			if (!$this->imap->hasMailBox($mailbox)) {
				if($this->imap->createMailBox($mailbox) && $imap->subscribeToMailBox($mailbox)) {
					echo "Could not create mailbox";
				}
			}*/


	}

	public function debug($m) {
		if($this->isVerbose) {
			if(is_string($m)) {
				echo $m;
			} else {
				var_dump($m);
			}
		}
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

	public function addSenderMailboxes() {
		foreach ($this->imap->getMessages() as $message) {
			$from = $message->getAddresses('from');
			
			$mailbox = $this->addressToMailbox($from);
		
			list($localpart,$domain) = explode('@', $from['address']);
			$rule = [
				'if envelope :matches :domain "From" "'.$domain.'" {',
				'  if envelope :matches :localpart "From" "'.$localpart.'" {',
				'    fileinto '.$mailbox.';'
			];
			$this->addRule($rule);
			$this->addMailbox($mailbox);
		}
		return $this;
	}
}
