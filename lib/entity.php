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

	const INSTRUCTIONS_ALLOWED_TAGS = '<p><br><b><i><strong><em><ul><ol><li>';

	public function __construct($appid, $email) {
		$this->appid = $appid;
		$this->email = $email;
		$this->created = new \DateTime();
	}
}

class Secret {
	public $reqid;
	public $secret;
	public $mac;
	public $created;

	const CIPHER = 'aes-128-cbc';

	public function __construct($reqid, $secret)
	{
		$this->reqid = $reqid;
		$this->secret = $secret;
		$this->created = new \DateTime();
	}

	protected function get_secret_mac($key) {
		return hash_hmac( 'sha1', $this->secret, $key, TRUE );
	}

	public function set_mac( $key ) {
		$this->mac = $this->get_secret_mac( $key );
	}

	public function is_mac_valid( $key ) {
		return hash_equals( $this->mac, $this->get_secret_mac( $key ) );
	}
}
