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

	public function run() {

		$this->bootstrap();

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
