<?php

namespace Vault;

require __DIR__ . '/schema.php';

class Installer_App extends Console_App {

	public function init_database_logging() {
		// Since most likely WE will be creating the database, there's
		// no point in trying to log to it.
	}

	public function run() {

		$this->bootstrap();

		foreach ( SCHEMA_INIT as $sql ) {
			$this->log->addInfo( 'Executing: ' . strtok( $sql, "\n" ) );
			$this->db->exec( $sql );
		}

		foreach ( SCHEMA_CREATE as $sql ) {
			$this->log->addInfo( 'Executing: ' . strtok( $sql, "\n" ) );
			$this->db->exec( $sql );
		}
	}
}
