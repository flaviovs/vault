<?php

namespace Vault;

class App {
	public $appid;
	public $key;
	public $secret;
	public $name;
	public $ping_url;

	public function __construct( $key, $secret, $name ) {
		$this->key = $key;
		$this->secret = $secret;
		$this->name = $name;
	}
}

class Request {
	public $reqid;
	public $appid;
	public $app_data;
	public $email;
	public $instructions;
	public $input_key;
	public $created;

	public function __construct($appid, $email) {
		$this->appid = $appid;
		$this->email = $email;
		$this->created = new \DateTime();
	}
}

class Secret {
	public $reqid;
	public $secret;
	public $created;

	const CIPHER = 'aes-128-cbc';

	public function __construct($reqid, $secret)
	{
		$this->reqid = $reqid;
		$this->secret = $secret;
		$this->created = new \DateTime();
	}
}
