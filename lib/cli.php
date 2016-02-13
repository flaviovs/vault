<?php

namespace Vault;

class CLI_App extends Console_App {

	protected function get_usage() {
		return 'COMMAND [COMMAND-ARGS]';
	}

	protected function handle_app_add() {
		$name = $this->getopt->get( 3 );
		if ( !$name ) {
		}
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
