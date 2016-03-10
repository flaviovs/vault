<?php

namespace Vault;

require __DIR__ . '/schema.php';

class Installer_App extends Console_App {

	public function init_database_logging() {
		// Since most likely WE will be creating the database, there's
		// no point in trying to log to it.
	}

	protected function perform_sql( $sql ) {
		$line1 = preg_replace( '/\s+/', ' ', $sql );
		if ( strlen( $line1 ) > 60 ) {
			$line1 = preg_replace( '/^(.{0,57}\b)\s.*/', '\1...', $line1 );
		}
		echo 'Executing "' . $line1 . "\"\n";

		try {
			$this->db->exec( $sql );
		} catch ( \PDOException $ex ) {
			$this->stdio->errln( '<<red>>Error executing database command:<<reset>>' );
			$this->stdio->errln( '<<bold>>' );
			fwrite( STDERR, preg_replace( '/^/m', "\t", $sql ) );
			$this->stdio->errln( '<<reset>>' );
			fwrite( STDERR, "\n" . $ex->getMessage() . "\n");
			return FALSE;
		}
		return TRUE;
	}

	protected function perform_array( array $sqls ) {
		foreach ( $sqls as $sql ) {
			if ( ! $this->perform_sql( $sql ) ) {
				return FALSE;
			}
		}
		return TRUE;
	}

	public function run() {

		$this->bootstrap();

		if ( ! $this->perform_array( SCHEMA_INIT ) ) {
			return 1;
		}

		if ( ! $this->perform_array( SCHEMA_CREATE ) ) {
			return 1;
		}

		return 0;
	}
}
