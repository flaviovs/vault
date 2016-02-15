<?php

namespace Vault;

class CLI_App extends Console_App {

	protected function get_usage() {
		return 'COMMAND [COMMAND-ARGS]';
	}

	protected function generate_app_key() {
		return base64_encode(openssl_random_pseudo_bytes(12));
	}

	protected function generate_app_secret() {
		return base64_encode(openssl_random_pseudo_bytes(30));
	}

	protected function handle_app_add() {
		$name = $this->getopt->get( 3 );
		if ( !$name ) {
			throw new \InvalidArgumentException( "Missing app name" );
		}
		$app = new App( $this->generate_app_key(),
						$this->generate_app_secret(),
						$name );
		$app->ping_url = $this->getopt->get( 4 );
		if ( $app->ping_url
		     && ! filter_var( $app->ping_url, FILTER_VALIDATE_URL ) ) {
			throw new \InvalidArgumentException( "Invalid Ping URL: "
			                                     . $app->ping_url );
		}
		$this->repo->add_app($app);
		$this->log->addNotice("Added app $app->key ($name)");
		echo "Key: " . $app->key . "\n";
		echo "Secret: " . $app->secret . "\n";
	}

	protected function handle_app() {
		$app_command = $this->getopt->get( 2 );

		switch ( $app_command ) {
		case 'add':
			$this->handle_app_add();
			break;
		default:
			throw new \InvalidArgumentException("Unkown app command '$app_command'");
		}
	}

	protected function process_command() {
		$command = $this->getopt->get( 1 );

		switch ( $command ) {
		case '':
			throw new \InvalidArgumentException("Invalid usage.");

		case 'app':
			$this->handle_app();
			break;
		default:
			throw new \InvalidArgumentException("Unkown command '$command'");
		}
	}

	public function run() {
		$this->bootstrap();

		try {
			$this->process_command();
		} catch ( \InvalidArgumentException $ex ) {
			$this->print_error( $ex->getMessage() );
			return 1;
		}

		return 0;
	}
}
