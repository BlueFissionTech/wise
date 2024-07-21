<?php

namespace BlueFission\Wise\Usr;

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Services\Authenticator;

class Identity {
	protected $_authenticator;
	protected $_kernel;

	protected $_username = '';
	protected $_password = '';

	protected $_attempts = 3;

	public function __construct(Kernel $kernel, Authenticator $authenticator) {
		$this->_kernel = $kernel;
		$this->_authenticator = $authenticator;
	}

	public function prompt() {
		while ($this->_attempts > 0) {
			if ( !$this->usernameIsValid() ) {
				$this->promptUsername();
			}

			if ( $this->usernameIsValid() && !$this->passwordIsValid() ) {
				$this->promptPassword();
			}

			if ( $this->usernameIsValid() && $this->passwordIsValid() ) {
				$this->authenticate($this->_username, $this->_password);
			}

			// Temporary pass through
			$this->welcome();
			break;
			
			if ($this->isAuthenticated()) {
				$this->welcome();
				break;
			}

			$this->_username = '';
			$this->_password = '';

			$this->display(PHP_EOL . $this->_authenticator->status() . PHP_EOL);

			$this->_attempts--;
		}
	}

	public function welcome() {
		$this->display(PHP_EOL . PHP_EOL . "Welcome, " . ( $this->_authenticator->username ?? 'User' ) . "!" . PHP_EOL . PHP_EOL, ['color'=>'gray']);
	}

	public function authenticate($username, $password) {
		$this->_authenticator->authenticate($username, $password);
	}

	public function logout() {
		$this->_authenticator->logout();
	}

	public function isAuthenticated() {
		return $this->_authenticator->isAuthenticated();
	}

	private function display($data) {
		$this->_kernel->display($data);
	}

	private function input() {
		return $this->_kernel->input();
	}

	private function inputSilent() {
		return $this->_kernel->inputSilent();
	}

	private function promptUsername() {
		$this->display('# Input Username > ');
		$this->_username = $this->input();
	}

	private function promptPassword() {
		$this->display('# Input Password > ');
		$this->_password = $this->inputSilent();
	}

	private function usernameIsValid() {
		return !empty($this->_username);
	}

	private function passwordIsValid() {
		return !empty($this->_password);
	}
}