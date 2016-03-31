<?php
/**
 * Miscelaneous classes for the client app
 */

namespace Vault;

/**
 * Represents a logged in user.
 */
class User {
	/**
	 * The user ID.
	 *
	 * @var mixed
	 */
	public $ID;

	/**
	 * The user e-mail address.
	 *
	 * @var string
	 */
	public $email;

	/**
	 * The user name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Constructs the object.
	 *
	 * @param mixed  $id The user ID.
	 * @param string $email The user e-mail address.
	 * @param string $name The user name.
	 */
	public function __construct( $id, $email, $name ) {
		$this->ID = $id;
		$this->email = $email;
		$this->name = $name;
	}
}
