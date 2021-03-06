<?php
/**
 * Contains the CLI install app class.
 */

namespace Vault;

require __DIR__ . '/schema.php';

/**
 * The CLI install app class.
 */
class Installer_App extends Console_App {

	/**
	 * {@inheritdocs}
	 */
	public function init_database_logging() {
		// Since most likely WE will be creating the database, there's
		// no point in trying to log to it.
	}

	/**
	 * Perform a single SQL command in the database.
	 *
	 * @param string $sql The SQL command.
	 */
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
			fwrite( STDERR, "\n" . $ex->getMessage() . "\n" );
			return false;
		}
		return true;
	}

	/**
	 * Perform a sequence os SQL commands in the database.
	 *
	 * The function will stop performing further commands if the
	 * current one fails.
	 *
	 * @param array $sqls An array containing SQL commands.
	 */
	protected function perform_array( array $sqls ) {
		foreach ( $sqls as $sql ) {
			if ( ! $this->perform_sql( $sql ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Return the current database schema version.
	 *
	 * @throws \PDOException in case of database errors.
	 */
	protected function get_schema_version() {
		try {
			$sth = $this->db->perform( 'SELECT MAX(version) AS version FROM vault_schema_versions' );
		} catch ( \PDOException $ex ) {
			if ( $ex->getCode() == '42S02' ) {
				// Table not found. We are at schema version -1.
				return -1;
			}
			throw $ex;
		}

		$row = $sth->fetch();
		return null === $row['version'] ? -1 : $row['version'];
	}

	/**
	 * Perform all SQL commands needed to bring the database up-to-date.
	 */
	protected function perform_updates() {
		$version = $this->get_schema_version();
		$updates = count( SCHEMA_UPDATES );

		if ( ( $updates - 1 ) == $version ) {
			$this->stdio->outln( '<<bold>>Your database is up-to-date.<<reset>>' );
			return true;
		} elseif ( -1 == $version ) {
			$this->stdio->outln( '<<bold>>Creating the database.<<reset>>' );
		} else {
			$this->stdio->outln( '<<bold>>Updating the database.<<reset>>' );
		}

		for ( $i = $version + 1; $i < $updates; $i++ ) {
			if ( ! $this->perform_sql( SCHEMA_UPDATES[ $i ] ) ) {
				return false;
			}
			$this->db->perform( 'INSERT INTO vault_schema_versions (version, updated) VALUES (?, NOW())', [ $i ] );
		}
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function run() {

		$this->bootstrap();

		if ( ! $this->perform_array( SCHEMA_INIT ) ) {
			return 1;
		}

		return $this->perform_updates() ? 0 : 1;
	}
}
