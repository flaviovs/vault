<?php

namespace Vault;

class VaultException extends \Exception {}

class Service {
	protected $repo;
	protected $log;
	protected $mailer_factory;

	public function __construct( Repository $repo,
	                             \Monolog\Logger $log,
	                             MailerFactory $mailer_factory ) {
		$this->repo = $repo;
		$this->log = $log;
		$this->mailer_factory = $mailer_factory;
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
		$this->repo->add_app($app);
		$this->log->addNotice("Added app $app->key ($name)");

		return $app;
	}
}
