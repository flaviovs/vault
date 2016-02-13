<?php

namespace Vault;

require __DIR__ . '/schema.php';

class Installer_App extends Vault_App {

	protected function init_basic_logging() {
		$handler = new \Monolog\Handler\StreamHandler('php://stderr');
		$handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"%channel%: %level_name%: %message% %context% %extra%\n"));
		$this->log->setHandlers([$handler]);
	}

	protected function handle_exception( \Exception $ex ) {
		$this->response->status->setCode(500);
	}

	protected function send_response() {
		// Do nothing. We send all output in handle_request()
	}

	protected function handle_request() {

		foreach ( SCHEMA as $sql ) {
			$this->log->addInfo( "Executing: " . strtok( $sql, "\n" ));
			try {
				$this->db->exec( $sql );
			} catch ( \PDOException $ex ) {
				$this->log->addError( $ex->getMessage() . ": " . $sql );
			}
		}
	}
}
