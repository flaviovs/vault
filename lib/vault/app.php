<?php

namespace Vault;

abstract class Vault_App {
	protected $name;
	protected $log;
	protected $conf;
	protected $db;
	protected $repo;
	protected $service;
	protected $views;

	const DEFAULT_CONFIG = [
		'maintenance' => [
			'expire_answered_requests_after' => '1 hour',
			'expire_unanswered_requests_after' => '1 day',
		],
		'debug' => [
			'api' => FALSE,
			'repeat_secret_input' => FALSE,
		],
	];

	abstract protected function init_basic_logging();
	abstract protected function run();

	public function __construct( $name ) {
		$this->name = $name;
		$this->log = new \Monolog\Logger( $name );

		$this->conf = new \UConfig\Config( static::DEFAULT_CONFIG );
		$this->conf->addHandler( new \UConfig\INIFileHandler( VAULT_ROOT . '/vault.ini' ) );

		$this->views = new \UView\Registry( VAULT_ROOT . '/view/vault' );

	}

	protected function load_config() {
		$this->conf->reload();
	}

	protected function init_database() {

		try {
			$dsn = $this->conf->get( 'db.default', 'dsn' );
			$user = $this->conf->get( 'db.default', 'user' );
			$password = $this->conf->get( 'db.default', 'password' );
		} catch ( ConfigException $ex ) {
			throw new \RuntimeException( 'Database not properly configured' );
		}

		$this->db = new \Aura\Sql\ExtendedPdo( $dsn, $user, $password );

		// Initialize our repository abstraction.
		$this->repo = new Repository( $this->db );

		$this->init_database_logging();
	}

	protected function init_database_logging() {
		// Initialize database logging
		$this->log->pushHandler( new DatabaseLoggingHandler( $this->db ) );
	}

	protected function init_service() {
		$this->service = new Service( $this->conf,
		                              $this->repo,
		                              $this->log,
		                              $this->views );
	}

	protected function bootstrap() {
		$this->init_basic_logging();
		$this->load_config();
		$this->init_database();
		$this->init_service();
	}
}
