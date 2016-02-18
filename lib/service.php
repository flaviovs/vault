<?php

namespace Vault;

class VaultException extends \Exception {}

class Service {
	protected $conf;
	protected $repo;
	protected $log;

	public function __construct( array $conf,
	                             Repository $repo,
	                             \Monolog\Logger $log) {
		$this->conf = $conf;
		$this->repo = $repo;
		$this->log = $log;
	}

	protected function generate_app_key() {
		return base64_encode(openssl_random_pseudo_bytes(12));
	}

	protected function generate_app_secret() {
		return base64_encode(openssl_random_pseudo_bytes(30));
	}

	public function add_app( $name, $ping_url) {
		$app = new App( $this->generate_app_key(),
		                $this->generate_app_secret(),
		                $name );
		if ( $ping_url && ! filter_var( $ping_url, FILTER_VALIDATE_URL ) ) {
			throw new VaultException( "Invalid Ping URL '$ping_url'" );
		}
		$app->ping_url = $ping_url;
		$this->repo->add_app($app);
		$this->log->addNotice("Added app $app->key ($name)");

		return [
			'key' => $app->key,
			'secret' => $app->secret,
		];
	}

	public function get_request_mac( Request $request, $key = NULL ) {
		if ( ! $key ) {
			$key = $request->input_key;
		}
		return hash_hmac( 'sha1',
		                  $request->reqid . ' ' . $request->email,
		                  $key,
		                  TRUE );
	}

	protected function get_input_url( Request $request ) {
		$input_hash = $this->get_request_mac( $request );

		return $this->conf[ 'url' ][ 'input' ]
			. '/request/' . $request->reqid . '/input?'
			. 'm=' . urlencode( base64_encode ( $input_hash ) );
	}

	protected function get_unlock_url( Request $request, $unlock_key ) {
		$mac = $this->get_request_mac( $request, $unlock_key );

		return $this->conf[ 'url' ][ 'unlock' ]
			. '/unlock/' . $request->reqid . '/input?'
			. 'm=' . urlencode( base64_encode ( $mac ) );
	}

	protected function email_request( Request $request ) {
		$input_url = $this->get_input_url( $request );

		$mail = new Mailer($this->conf, $this->log);

		$mail->addAddress($request->email);
		$mail->Subject = "## Input URL ##";
		$mail->Body = "Input the secret here: $input_url";

		if ( ! $mail->send() ) {
			$this->log->addError( "Failed to send e-mail for request $request->reqid: " . $mail->ErrorInfo );
		}
	}

	public function register_request($key, $email, $instructions, $app_data) {
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \InvalidArgumentException("Invalid e-mail '$email'");
		}

		$app = $this->repo->find_app_by_key($key);

		$request = new Request($app->appid, $email);
		$request->instructions = $instructions;
		$request->app_data = $app_data;
		$request->input_key = base64_encode(openssl_random_pseudo_bytes(24));

		$this->repo->add_request($request);
		$this->log->info("Added request $request->reqid for '$email' by $key");

		$this->email_request($request);

		return [
			'reqid' => $request->reqid,
		];
	}

	protected function ping_back( Request $request, $unlock_key ) {
		$app = $this->repo->find_app( $request->appid );
		if ( ! $app->ping_url ) {
			return;
		}

		$postdata = [
			'reqid' => $request->reqid,
			'unlock_key' => $unlock_key,
			'app_data' => $request->app_data,
			'unlock_url' => $this->get_unlock_url( $request, $unlock_key ),
			'mac' => base64_encode( hash_hmac( 'sha1',
			                                   $request->reqid . ' '
			                                   . $unlock_key . ' '
			                                   . $request->app_data,
			                                   $app->secret,
			                                   TRUE ) )
		];

		if ( $this->log->isHandling(\Monolog\Logger::DEBUG) ) {
			$this->log->addDebug( "Pinging $app->ping_url",
			                      [ 'postdata' => $postdata ] );
		}

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $app->ping_url );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Vault' );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 20 );
		curl_setopt( $ch, CURLOPT_POST, TRUE );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postdata );

		$res = curl_exec( $ch );
		if ( curl_getinfo( $ch, CURLINFO_HTTP_CODE ) == 200 ) {
			$this->log->addInfo( "Pinged back $app->key@$app->ping_url for request $request->reqid" );
			$this->repo->record_ping( $request->reqid );
		} else {
			$this->log->addNotice( "Failed to ping back $app->key@$app->ping_url for request $request->reqid: " . curl_error( $ch ) );
		}
	}

	public function register_secret( Request $request , $plaintext ) {

		$unlock_key = base64_encode(openssl_random_pseudo_bytes(24));

		$iv_size = openssl_cipher_iv_length(Secret::CIPHER);
		$iv = openssl_random_pseudo_bytes($iv_size);

		$secret = new Secret( $request->reqid,
		                      $iv . openssl_encrypt( $plaintext,
		                                             Secret::CIPHER,
		                                             $unlock_key,
		                                             OPENSSL_RAW_DATA,
		                                             $iv ) );
		$secret->set_mac( $unlock_key );

		$this->repo->begin();
		$this->repo->add_secret( $secret );
		$this->repo->clear_request_input_key( $request );
		$this->ping_back( $request, $unlock_key );
		$this->repo->commit();

		return [
			'unlock_key' => $unlock_key,
		];
	}

	public function unlock_secret( Secret $secret, $key ) {
		$this->repo->record_unlock( $secret );

		$iv_size = openssl_cipher_iv_length(Secret::CIPHER);

		return openssl_decrypt( substr($secret->secret, $iv_size),
		                        Secret::CIPHER,
		                        $key,
		                        OPENSSL_RAW_DATA,
		                        substr($secret->secret, 0, $iv_size) );
	}
}
