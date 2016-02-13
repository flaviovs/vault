<?php

namespace Vault;

require __DIR__ . '/schema.php';

class Installer_App extends Console_App {

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
