<?php

namespace Vault;

class User {
	public $ID;
	public $email;
	public $name;

	public function __construct( $id, $email, $name ) {
		$this->ID = $id;
		$this->email = $email;
		$this->name = $name;
	}
}
