<?php

namespace Vault;

class VaultException extends \Exception {}

class Service {
	protected $conf;
	protected $repo;
	protected $log;
	protected $views;
	protected $mailer_factory;

	public function __construct( \UConfig\Config $conf,
	                             Repository $repo,
	                             \Monolog\Logger $log,
	                             \UView\Registry $views,
	                             Mailer_Factory $mailer_factory ) {
		$this->conf = $conf;
		$this->repo = $repo;
		$this->log = $log;
		$this->views = $views;
		$this->mailer_factory = $mailer_factory;
	}

	protected function generate_app_key() {
		return base64_encode( openssl_random_pseudo_bytes( 12 ) );
	}

	protected function generate_app_secret() {
		return base64_encode( openssl_random_pseudo_bytes( 30 ) );
	}

	public function add_app( $name, $ping_url ) {
		$secret = $this->generate_app_secret();
		$app = new App( $this->generate_app_key(),
		                password_hash( $secret, PASSWORD_DEFAULT ),
		                $this->generate_app_secret(),
		                $name, $ping_url );
		if ( $ping_url && ! filter_var( $ping_url, FILTER_VALIDATE_URL ) ) {
			throw new VaultException( "Invalid Ping URL '$ping_url'" );
		}
		$this->repo->add_app( $app );
		$this->log->addNotice( "Added app $app->key ($name)" );

		return [
			'key' => $app->key,
			'secret' => $secret,
			'vault_secret' => $app->vault_secret,
		];
	}

	public function get_request_mac( Request $request, $key = null ) {
		if ( ! $key ) {
			$key = $request->input_key;
		}
		return hash_hmac( 'sha1',
		                  $request->reqid . ' ' . $request->email,
		                  $key,
		                  true );
	}

	protected function get_input_url( Request $request ) {
		$input_hash = $this->get_request_mac( $request );

		return $this->conf->get( 'url', 'input' )
			. '/request/' . $request->reqid . '/input?'
			. 'm=' . urlencode( base64_encode( $input_hash ) );
	}

	protected function get_unlock_url( Request $request, $unlock_key ) {
		$mac = $this->get_request_mac( $request, $unlock_key );

		return $this->conf->get( 'url', 'unlock' )
			. '/unlock/' . $request->reqid . '/unlock?'
			. 'k=' . urlencode( base64_encode( $unlock_key ) ) . '&'
			. 'm=' . urlencode( base64_encode( $mac ) );
	}

	protected function email_request( Request $request ) {
		$mail = $this->mailer_factory->new_mailer();

		$body = $this->views->get( 'email-request' );
		$body->set( 'input_url', $this->get_input_url( $request ) );

		$mail->addAddress( $request->email );

		// Ignore camel-case properties in $mail
		// @codingStandardsIgnoreStart
		$mail->Subject = 'We need your information';
		$mail->Body = (string) $body;
		// @codingStandardsIgnoreEnd

		if ( ! $mail->send() ) {
			// @codingStandardsIgnoreStart
			$this->log->addError( "Failed to send e-mail for request $request->reqid: " . $mail->ErrorInfo );
			// @codingStandardsIgnoreEnd
		}
	}

	public function register_request( $key, $email, $instructions, $app_data ) {
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \InvalidArgumentException( "Invalid e-mail '$email'" );
		}

		$app = $this->repo->find_app_by_key( $key );

		$request = new Request( $app->appid, $email );
		$request->instructions = $instructions;
		$request->app_data = $app_data;
		$request->input_key = base64_encode( openssl_random_pseudo_bytes( 24 ) );

		$this->repo->add_request( $request );
		$this->log->addNotice( "Added request $request->reqid for '$email'",
		                       [
			                       'app' => $app,
		                       ] );

		$this->email_request( $request );

		return [
			'reqid' => $request->reqid,
		];
	}

	protected function ping_back( App $app, $subject, array $payload ) {

		$postdata = [
			's' => $subject,
			'p' => json_encode( $payload ),
		];

		if ( $this->log->isHandling( \Monolog\Logger::DEBUG ) ) {
			$this->log->addDebug( "Pinging $app->ping_url",
			                      [
				                      'payload' => $payload,
				                      'postdata' => $postdata,
			                      ] );
		}

		$postdata['m'] = hash_hmac( 'sha1',
		                            $postdata['s'] . ' ' . $postdata['p'],
		                            $app->vault_secret,
		                            true );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $app->ping_url );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Vault' );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 20 );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postdata );

		$res = curl_exec( $ch );
		if ( false === $res ) {
			throw new VaultException( curl_error( $ch ) );
		} else {
			$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			if ( 200 !== $code ) {
				throw new VaultException( "$app->ping_url returned HTTP $code" );
			}
		}
	}

	protected function ping_back_submission( Request $request, $unlock_key ) {
		$app = $this->repo->find_app( $request->appid );
		if ( ! $app->ping_url ) {
			return;
		}

		$payload = [
			'reqid' => $request->reqid,
			'unlock_key' => $unlock_key,
			'app_data' => $request->app_data,
			'unlock_url' => $this->get_unlock_url( $request, $unlock_key ),
		];

		try {
			$this->ping_back( $app, 'submission', $payload );

			$this->log->addInfo( "Pinged $app->key@$app->ping_url for request $request->reqid" );
		} catch ( VaultException $ex ) {
			$this->log->addNotice( "Failed to ping back $app->key@$app->ping_url for request $request->reqid: " . $ex->getMessage() );
			throw $ex;
		}
	}

	public function register_secret( Request $request, $plaintext ) {

		$unlock_key = base64_encode( openssl_random_pseudo_bytes( 24 ) );

		$iv_size = openssl_cipher_iv_length( Secret::CIPHER );
		$iv = openssl_random_pseudo_bytes( $iv_size );

		$secret = new Secret( $request->reqid,
		                      $iv . openssl_encrypt( $plaintext,
		                                             Secret::CIPHER,
		                                             $unlock_key,
		                                             OPENSSL_RAW_DATA,
		                                             $iv ) );
		$secret->set_mac( $unlock_key );

		$debug_repeat_secret_input = $this->conf->get( 'debug',
		                                               'repeat_secret_input' );

		$this->repo->begin();
		if ( $debug_repeat_secret_input ) {
			$this->log->addWarning( 'debug.repeat_secret_input is enabled' );
			$this->repo->delete_secret( $secret );
		}
		$this->repo->add_secret( $secret );
		if ( ! $debug_repeat_secret_input ) {
			$this->repo->clear_request_input_key( $request );
		}

		$this->ping_back_submission( $request, $unlock_key );

		$this->repo->commit();

		return [
			'unlock_key' => $unlock_key,
		];
	}

	public function unlock_secret( Secret $secret, $key ) {
		$this->repo->record_unlock( $secret );

		$iv_size = openssl_cipher_iv_length( Secret::CIPHER );

		return openssl_decrypt( substr( $secret->secret, $iv_size ),
		                        Secret::CIPHER,
		                        $key,
		                        OPENSSL_RAW_DATA,
		                        substr( $secret->secret, 0, $iv_size ) );
	}

	public function delete_answered_requests() {
		$period = $this->conf->get( 'maintenance',
		                            'expire_answered_requests_after' );
		$before = new \DateTime();
		$before->sub( \DateInterval::createFromDateString( $period ) );

		$this->repo->delete_answered_requests( $before );
	}

	public function delete_unanswered_requests() {
		$period = $this->conf->get( 'maintenance',
		                            'expire_unanswered_requests_after' );
		$before = new \DateTime();
		$before->sub( \DateInterval::createFromDateString( $period ) );

		$this->repo->delete_unanswered_requests( $before );
	}

	public function maintenance() {
		$this->delete_answered_requests();
		$this->delete_unanswered_requests();
	}
}
