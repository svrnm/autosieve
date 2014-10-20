<?php

namespace spec\ELearningAG\AutoSieve;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use ELearningAG\AutoSieve\Interfaces\IMAPHandler;

class AutoSieveSpec extends ObjectBehavior
{
	function let($imap, $sieve) {
			//$imap->beADoubleOf('ELearningAG\AutoSieve\Interfaces\IMAPHandler');
			$imap->implement('\ELearningAG\AutoSieve\Interfaces\IMAPHandler');
			$sieve->implement('\ELearningAG\AutoSieve\Interfaces\SieveHandler');

			$this->beConstructedWith($imap, $sieve);
	}

    function it_is_initializable()
    {
			$this->shouldHaveType('ELearningAG\AutoSieve\AutoSieve');
			
	}

	function it_builds_sieve_scripts() {
			$this->buildScript()->shouldReturn(implode(PHP_EOL,[
					'require ["fileinto","variables","mailbox","envelope"];',
			      	'# rule:[Autosieve-Do-Not-Touch]',
					'if true {',
					'',
					'}'
					]
			));
	}

	function it_keeps_new_sieve_rules() {
			$this->addRule(['if true {','}'])->buildScript()->shouldReturn(implode(PHP_EOL,[
                    'require ["fileinto","variables","mailbox","envelope"];',
                    '# rule:[Autosieve-Do-Not-Touch]',
                    'if true {',
                    'if true {',
					'}',
                    '}'
                    ]
            ));
	}

	function it_keeps_required_imap_mailboxes() {
			$this->addMailbox('INBOX.Test')->getMailboxes()->shouldReturn(['INBOX.Test']);
	}

	function it_converts_an_email_address_to_mailbox_name() {
			$this->addressToMailbox(['name' => 'Test Tester', 'address' => 'test@example.com'])->shouldReturn('INBOX.Example.Test Tester');
			$this->addressToMailbox(['name' => '', 'address' => 'tester.test@example.com'])->shouldReturn('INBOX.Example.tester-test');
	}

	function it_creates_sieve_rules_for_new_addresses($imap, \Fetch\Message $message) {
			$message->getAddresses('from')->willReturn(['name' => 'Test Tester', 'address' => 'test@example.com']);
			$imap->getMessages()->willReturn([
				$message
			]);
			$this->addSenderMailboxes()->buildScript()->shouldReturn(implode(PHP_EOL,[
		      'require ["fileinto","variables","mailbox","envelope"];',
		      '# rule:[Autosieve-Do-Not-Touch]',
		      'if true {',
		      'if envelope :matches :domain "From" "example.com" {',
		      '  if envelope :matches :localpart "From" "test" {',
		      '    fileinto INBOX.Example.Test Tester;',
		      '}'
			]));
	}
}
