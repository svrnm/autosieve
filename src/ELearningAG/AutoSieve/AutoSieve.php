<?php
namespace ELearningAG\AutoSieve;

use ELearningAG\AutoSieve\Interfaces\IMAPHandler;
use ELearningAG\AutoSieve\Interfaces\SieveHandler;

/**
 * Create sieve rules by crawling the inbox of the user. Currently there is only one "magic" function:
 * 
 * - addSenderMailboxes(): Add a imap mailbox for each new sender and a sieve rule to move all messages
 * of this sender in the created and subsribed folder.
 *
 * @author Severin Neumann <s.neumann@elearning-ag.de>
 * @copyright 2014 die eLearning AG
 * @license GPL-3.0 
 */
class AutoSieve {
		/**
		 * The handler for all imap related functionality
		 *
		 * @var ELearningAG\AutoSieve\Interfaces\IMAPHandler;
		 */
		protected $imap;

		/**
		 * The handler for all sieve related functionality
		 *
		 * @var ELearningAG\AutoSieve\Interfaces\SieveHandler
		 */
		protected $sieve;

		/**
		 * The name of the sieve scripted, which will be stored on the server.
		 *
		 * @var string
		 */
		protected $scriptName = 'autosieve';

		/**
		 * The sieve plugins which are required to process the rules created by the given instance.
		 *
		 * @var [string]
		 */
		protected $sievePlugins = ['fileinto','variables','mailbox','envelope'];

		/**
		 * A "name" for the rule. This is especially for roundcube user, where the editor is not
		 * able to display the given rules.
		 *
		 * @var [string]
		 */
		protected $sieveRuleName = 'Autosieve-Do-Not-Touch';

		/**
		 * The "header" is currently the old script filed loaded from the server before new rules
		 * are appended
		 *
		 * @var [string]
		 */
		protected $sieveHeader = [];

		/**
		 * The rules which will be appended to the script when save() is called
		 *
		 * @var array
		 */
		protected $rules = [];

		/**
		 * The mailboxes which will be created and subscribed to when save() is called
		 *
		 * @var [string]
		 */
		protected $mailboxes = [];

		/**
		 * The messages which will be moved to different mailboxes when save() is called.
		 * The keys of this array are the mailboxes.
		 *
		 * @var array
		 */
		protected $messages = [];

		/**
		 * Create a new instance of AutoSieve. The two parameters $imap and $sieve are
		 * the handlers for the functionality of the two used protocols
		 *
		 * @param ELearningAG\AutoSieve\Interfaces\IMAPHandler $imap
		 * @param ELearningAG\AutoSieve\Interfaces\SieveHandler $sieve
		 */
		public function __construct(IMAPHandler $imap, SieveHandler $sieve) {
				$this->imap = $imap;
				$this->sieve = $sieve;
		}

		/**
		 * A builder function to create a new instance of AutoSieve. The method expects
		 * a configuration for imap and sieve. If sieve is not configured, the imap credentials
		 * and port 4190 are used as defaults.
		 *
		 * The expected keys of both arrays are:
		 *
		 * - host
		 * - port
		 * - user
		 * - password
		 *
		 * @param array $imapConfig
		 * @param array $sieveConfig
		 * @return \ELearningAG\AutoSieve\AutoSieve
		 */
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

		/**
		 * Change the name of the sieve script.
		 *
		 * @return $this
		 */
		public function setScriptName($name) {
				$this->scriptName = $name;
				return $this;
		}

		/**
		 * Returns the name of the sieve script
		 *
		 * @return string
		 */
		public function getScriptName() {
				return $this->scriptName;	
		}

		/**
		 * Build and return the sieve script which will be uploaded to
		 * the sieve server.
		 *
		 * @return string
		 */
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


		/**
		 * Upload the new sieve rules to the server
		 *
		 * @return $this;
		 */
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
				 $this->rules = [];
				 $this->sieveHeader = [];
				 return $this;
		 }

		/**
		 * Create new imap mailboxes and move mails to these boxes.
		 *
		 * @return $this
		 */
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
				$this->mailboxes = [];
				$this->messages = [];
				return $this;
		}

		/**
		 * Save all rules, mailboxes and messages
		 *
		 * @return $this
		 */
		public function save() {
				return $this->saveSieve()->saveImap();
		}

		/**
		 * Convert a email address to a mailbox name, i.e.
		 *
		 * name <localpart@domain.tld> => INBOX.domain.name
		 *
		 * and if name is not set INBOX.domain.localpart
		 *
		 * @return string
		 */
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

		/**
		 * Add a new sieve rule which will be added to the script
		 * when save() is called.
		 *
		 * @return $this;
		 */
		public function addRule($rule) {
				$this->rules[] = $rule;
				return $this;
		}

		/**
		 * Add a new mailbox which will be created and subscribed to
		 * when save() is called.
		 *
		 * @return $this
		 */
		public function addMailbox($mailbox) {
				$this->mailboxes[] = $mailbox;
				return $this;
		}

		/**
		 * Get the currently stored and not yet created mailboxes.
		 *
		 * @return array
		 */
		public function getMailboxes() {
				return $this->mailboxes;
		}

		/**
		 * Add a $message to a $mailbox. The message will be moved
		 * to the given mailbox when save() is called.
		 */
		public function addMessageToMailbox($message, $mailbox) {
				$this->messages[$mailbox][] = $message;
		}

		/**
		 * Crawl the INBOX of the given imap user and for each emailaddress
		 * a sieve rule is created to move any further mail of this sender
		 * to the folder INBOX.domain.name.
		 *
		 * @return $this
		 */
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
