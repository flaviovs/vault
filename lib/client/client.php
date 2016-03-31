<?php
/**
 * Contains the Vault client class
 */

namespace Vault;

/**
 * Exception thrown by the client to signal a problem.
 */
class VaultClientException extends \Exception {}

/**
 * The Vault client.
 */
class VaultClient {

	/**
	 * The URL endpoint connection to the Vault server.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * The client key.
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * The client secret.
	 *
	 * @var string
	 */
	protected $secret;

	/**
	 * Constructs the object.
	 *
	 * @param string $url    The URL endpoint connection to the Vault server.
	 * @param string $key    The client key.
	 * @param string $secret The client secret.
	 */
	public function __construct( $url, $key, $secret ) {
		$this->url = $url;
		$this->key = $key;
		$this->secret = $secret;
	}

	/**
	 * Sends a low-level call to the Vault server.
	 *
	 * @param string $name The call name.
	 * @param array  $args Call arguments.
	 *
	 * @throws VaultClientException if the call fails.
	 */
	protected function call( $name, array $args ) {

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $this->url . '/' . $name );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $args );
		curl_setopt( $ch, CURLOPT_USERPWD, $this->key . ':' . $this->secret );

		$res = curl_exec( $ch );

		$error = null;
		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		if ( 200 != $code ) {
			$error = "$this->url returned HTTP $code";
		} elseif ( empty( $res ) ) {
			$error = curl_error( $ch );
			if ( ! $error ) {
				$error = 'Unknown error returned by API';
			}
		}
		curl_close( $ch );

		if ( $error ) {
			throw new VaultClientException( $error );
		}

		// FIXME: handle JSON decoding errors.
		return json_decode( $res, true );
	}

	/**
	 * Proxy call for adding a Vault request.
	 *
	 * @param string $email        The user e-mail address.
	 * @param string $instructions The request instructions (optional).
	 * @param string $app_data     Application specific request data (optional).
	 */
	public function add_request( $email, $instructions = null, $app_data = null ) {
		return $this->call( 'request',
		                    [
			                    'email' => $email,
			                    'instructions' => $instructions,
			                    'app_data' => $app_data,
		                    ] );
	}
}
