<?php

namespace Vault;

class CLI_App extends Console_App {

	protected function get_usage() {
		return 'COMMAND [COMMAND-ARGS]';
	}

	protected function handle_app_add() {
		$name = $this->getopt->get( 3 );
		if ( !$name ) {
			throw new \InvalidArgumentException( "Missing app name" );
		}
		$app = $this->service->add_app( $name, $this->getopt->get( 4 ) );
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

	protected function handle_request() {
		$appkey = $this->getopt->get( 2 );
		$email = $this->getopt->get( 3 );

		if ( !( $appkey && $email ) ) {
			throw new \InvalidArgumentException("Usage 'request APPKEY EMAIL'");
		}

		$app_data = $this->getopt->get(4);

		$instructions = file_get_contents( "php://stdin" );

		$res = $this->service->add_request($appkey, $email, $instructions, $app_data);

		echo json_encode($res, JSON_PRETTY_PRINT);
		echo "\n";
	}

	protected function process_command() {
		$command = $this->getopt->get( 1 );

		switch ( $command ) {
		case '':
			throw new \InvalidArgumentException("Invalid usage.");

		case 'app':
			$this->handle_app();
			break;

		case 'request':
			$this->handle_request();
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
