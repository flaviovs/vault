<?php

namespace Vault;

class VaultClientException extends \Exception {}

class VaultClient {

	protected $url;
	protected $key;
	protected $secret;

	public function __construct( $url, $key, $secret ) {
		$this->url = $url;
		$this->key = $key;
		$this->secret = $secret;
	}

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

		// FIXME: handle JSON decoding errors
		return json_decode( $res, true );
	}

	public function add_request( $email, $instructions = null, $app_data = null ) {
		return $this->call( 'request',
		                    [
			                    'email' => $email,
			                    'instructions' => $instructions,
			                    'app_data' => $app_data,
		                    ] );
	}
}
