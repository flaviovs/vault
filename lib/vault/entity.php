<?php
/**
 * Contains Vault entity definitions.
 */

namespace Vault;

/**
 * The App entity class.
 */
class App {
	/**
	 * The internal app id.
	 *
	 * @var int
	 */
	public $appid;

	/**
	 * The app key.
	 *
	 * @var string
	 */
	public $key;

	/**
	 * The app secret.
	 *
	 * @var string
	 */
	public $secret;

	/**
	 * The Vault secret associated with this app.
	 *
	 * This secret is used to authenticate/decrypt data that is sent
	 * by Vault to this App (for example, in ping-backs).
	 *
	 * @var string
	 */
	public $vault_secret;

	/**
	 * The human-readable app name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The app ping URL.
	 *
	 * @var string
	 */
	public $ping_url;

	/**
	 * Constructs the object.
	 */
	public function __construct( $key, $secret, $vault_secret, $name, $ping_url ) {
		$this->key = $key;
		$this->secret = $secret;
		$this->vault_secret = $vault_secret;
		$this->name = $name;
		$this->ping_url = $ping_url;
	}
}

/**
 * The Request entity class.
 */
class Request {
	/**
	 * The request ID.
	 *
	 * @var int
	 */
	public $reqid;

	/**
	 * The ID of the app that created this request.
	 *
	 * @var int
	 */
	public $appid;

	/**
	 * Optional app data associated with this request.
	 *
	 * This is an opaque, app-specific data value that is stored on
	 * the database along the request.
	 *
	 * @var string
	 */
	public $app_data;

	/**
	 * The e-mail address of the user associated with the request.
	 *
	 * @var string
	 */
	public $email;

	/**
	 * The additional instuctions to be  associated with the request.
	 *
	 * Optional. Set to null if no additional instruction was
	 * specified when the request was created.
	 *
	 * @var string
	 */
	public $instructions;

	/**
	 * The input key.
	 *
	 * This key is used to validate the request MAC that was sent to
	 * the user in the request e-mail. It is cleared when the user
	 * input the secret.
	 *
	 * @var string
	 */
	public $input_key;

	/**
	 * The date/time the request was created.
	 *
	 * @var \DateTime
	 */
	public $created;

	/**
	 * Constructs the object.
	 */
	public function __construct( $appid, $email ) {
		$this->appid = $appid;
		$this->email = $email;
		$this->created = new \DateTime();
	}
}

/**
 * The Secret entity class.
 */
class Secret {
	/**
	 * The request ID that this secret is associated with.
	 *
	 * @var int
	 */
	public $reqid;

	/**
	 * The encrypted secret.
	 *
	 * This is a unencoded binary string.
	 *
	 * @var string
	 */
	public $secret;

	/**
	 * The encrypted secret MAC.
	 *
	 * Calculated as:
	 *
	 *     MAC = HMAC-SHA1($this->secret, UNLOCK-KEY)
	 *
	 * @var string
	 */
	public $mac;

	/**
	 * The date/time the secret was entered.
	 *
	 * @var \DateTime
	 */
	public $created;

	/**
	 * This is the cypher algorithm used to encrypt secrets.
	 *
	 * **CAUTION**: Changing this value will invalidate all previously
	 * secrets already in the database.
	 */
	const CIPHER = 'aes-128-cbc';

	/**
	 * Constructs the object.
	 */
	public function __construct( $reqid, $secret ) {
		$this->reqid = $reqid;
		$this->secret = $secret;
		$this->created = new \DateTime();
	}

	/**
	 * Returns the MAC to validate an (encrypted) secret.
	 *
	 * @param string $key The key to be used to calculate the MAC.
	 */
	protected function get_secret_mac( $key ) {
		return hash_hmac( 'sha1', $this->secret, $key, true );
	}

	/**
	 * Update the 'mac' property for a shared key.
	 *
	 * @param string $key The key to be used to calculate the MAC.
	 */
	public function set_mac( $key ) {
		$this->mac = $this->get_secret_mac( $key );
	}

	/**
	 * Verify if the current MAC is valid for an unlock key.
	 *
	 * @param string $key The key to be used to verify the MAC.
	 */
	public function is_mac_valid( $key ) {
		return hash_equals( $this->mac, $this->get_secret_mac( $key ) );
	}
}
