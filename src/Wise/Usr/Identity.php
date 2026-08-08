<?php

namespace BlueFission\Wise\Usr;

use BlueFission\Wise\Arc\Kernel;
use BlueFission\Services\Authenticator;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Wise\Usr\Profile;

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

	public function profile(): Profile
	{
		$id = '';
		$authId = $this->_authenticator->id ?? null;
		$authUsername = $this->_authenticator->username ?? null;

		if (Val::is($authId) && Str::isNotEmpty((string)$authId)) {
			$id = (string)$authId;
		} elseif (Val::is($authUsername) && Str::isNotEmpty((string)$authUsername)) {
			$id = (string)$authUsername;
		}

		if ($id === '') {
			$id = 'guest';
		}

		$roles = [];
		$authRoles = $this->_authenticator->roles ?? null;
		if (Val::is($authRoles) && Arr::is($authRoles)) {
			$roles = $authRoles;
		}

		if (Arr::isEmpty($roles)) {
			$roles = [$this->isAuthenticated() ? 'user' : 'guest'];
		}

		return new Profile($id, $roles);
	}

	private function display($data) {
		$this->_kernel->send($data);
	}

	private function input() {
		return $this->_kernel->input();
	}

	private function inputSilent() {
		return $this->_kernel->inputSilent();
	}

	private function promptUsername() {
		$this->display(PHP_EOL.'# Input Username > ');

		while (Str::pos($this->_username, PHP_EOL) === false) {
			// append to username while accounting for backspace
			$this->_username .= $this->input();
			
			if ($this->input() === "\x7F") {
				$this->_username = Str::use()->sub(0, -1);
			}
			
			usleep(100000);
		}
	}

	private function promptPassword() {
		$this->display(PHP_EOL.'# Input Password > ');

		while (Str::pos($this->_password, PHP_EOL) === false) {
			// append to password while accounting for backspace
			$this->_password .= $this->inputSilent();
			
			if ($this->input() === "\x7F") {
				$this->_password = Str::use()->sub(0, -1);
			}
			
			usleep(100000);
		}
	}

	private function usernameIsValid() {
		return Str::isNotEmpty($this->_username);
	}

	private function passwordIsValid() {
		return Str::isNotEmpty($this->_password);
	}
}
