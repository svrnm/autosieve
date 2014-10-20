<?php
namespace ELearningAG\AutoSieve\Interfaces;

interface SieveHandler {
			public function connect($host, $port);
			public function login($user, $password);
			public function getExtensions();
			public function getScript($scriptname);
			public function haveSpace($scriptname, $size);
			public function installScript($scriptname, $script, $makeactive = false);
			public function listScripts();
}
