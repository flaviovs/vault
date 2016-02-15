<?php

namespace Vault;

abstract class Vault_App {

	protected $log;
	protected $conf;
	protected $db;
	protected $repo;
	protected $service;

	abstract protected function init_basic_logging();
	abstract protected function run();

	public function __construct($name) {
		$this->log = new \Monolog\Logger($name);
	}

	protected function load_config() {
		$ini = VAULT_ROOT . '/config.ini';
		if ( file_exists( $ini ) ) {
			$this->conf = parse_ini_file( $ini, TRUE );
		} else {
			$this->conf = [];
		}
	}

	protected function init_database() {
		if ( empty( $this->conf[ 'db.default' ] ) ) {
			throw new \RuntimeException('Database configuration missing');
		}

		$conf = $this->conf[ 'db.default' ];
		$this->db = new \Aura\Sql\ExtendedPdo( $conf[ 'dsn' ],
											   $conf[ 'user' ],
											   $conf[ 'password' ]);

		// Initialize our repository abstraction.
		$this->repo = new Repository($this->db);

		$this->init_database_logging();
	}

	protected function init_database_logging() {
		// Initialize database logging
		$this->log->pushHandler(new DatabaseLoggingHandler($this->db));
	}

	protected function init_service() {
		$this->service = new Service( $this->conf,
		                              $this->repo,
		                              $this->log );
	}

	protected function bootstrap() {
		$this->init_basic_logging();
		$this->load_config();
		$this->init_database();
		$this->init_service();
	}
}
