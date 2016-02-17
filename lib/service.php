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

	public function get_input_hash( Request $request ) {
		return hash_hmac( 'sha1',
		                  'input ' . $request->reqid . ' ' . $request->email,
		                  $request->input_key,
		                  TRUE );
	}

	protected function get_input_url( Request $request ) {
		$input_hash = $this->get_input_hash( $request );

		return $this->conf[ 'url' ][ 'input' ]
			. '/request/' . $request->reqid . '/input?'
			. 'm=' . urlencode( base64_encode ( $input_hash ) );
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

		$app = $this->repo->find_app($key);

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
		$this->repo->begin();
		$this->repo->add_secret( $secret );
		$this->repo->clear_request_input_key( $request );
		$this->repo->commit();

		return [
			'unlock_key' => $unlock_key,
		];
	}
}
