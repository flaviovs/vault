<?php
/**
 * Contains the top-level Vault app class.
 */

namespace Vault;

/**
 * The top-level Vault app class.
 */
abstract class Vault_App {

	/**
	 * The app name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The general logger.
	 *
	 * This logger is used to record ordinary app events, such as
	 * invalid requests, errors, etc.
	 *
	 * @var Monolog/Logger
	 */
	protected $log;

	/**
	 * The audit logger.
	 *
	 * This logger is used to record model events, such as new
	 * requests, ping-baks, etc.
	 *
	 * @var Monolog/Logger
	 */
	protected $audit;

	/**
	 * The configuration object.
	 *
	 * @var UConfig\Config
	 */
	protected $conf;

	/**
	 * The database connection.
	 *
	 * @var \Aura\Sql\ExtendedPDO
	 */
	protected $db;

	/**
	 * The entity repository.
	 *
	 * @var Repository
	 */
	protected $repo;

	/**
	 * The Vault service object.
	 *
	 * @var Service
	 */
	protected $service;

	/**
	 * The views registry.
	 *
	 * @var UViews/Registry
	 */
	protected $views;

	/**
	 * The default configuration.
	 *
	 * @var array
	 */
	const DEFAULT_CONFIG = [
		'maintenance' => [
			'expire_answered_requests_after' => '1 hour',
			'expire_unanswered_requests_after' => '1 day',
		],
		'logging' => [
			'general_level' => 'info',
			'audit_level' => 'notice',
		],
		'debug' => [
			'api' => false,
			'repeat_secret_input' => false,
		],
	];

	/**
	 * Initialize basic logging.
	 *
	 * At the point this method is called, the app object is fully
	 * constructed, but may not have set up all used resources. For
	 * instance, the database connection is *not* set up at the time
	 * this method is called.
	 *
	 * @see init_database_logging()
	 */
	abstract protected function init_basic_logging();

	/**
	 * The app main entry point.
	 */
	abstract protected function run();

	/**
	 * Constructs the object.
	 *
	 * @param string $name The app name. This is mostly used for logging.
	 */
	public function __construct( $name ) {
		$this->name = $name;
		$this->log = new \Monolog\Logger( $name );
		$this->audit = new \Monolog\Logger( $name );

		$this->conf = new \UConfig\Config( static::DEFAULT_CONFIG );
		$this->conf->addHandler( new \UConfig\INIFileHandler( VAULT_ROOT . '/vault.ini' ) );

		$this->views = new \UView\Registry( VAULT_ROOT . '/view/vault' );

	}

	/**
	 * Load the app configuration.
	 */
	protected function load_config() {
		$this->conf->reload();
	}

	/**
	 * Initializes the database.
	 *
	 * This will in turn initialize the repository and database audit
	 * logging.
	 *
	 * @throws \RuntimeException if a problem is found.
	 */
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

	/**
	 * Initializes database audit logging.
	 */
	protected function init_database_logging() {
		$this->audit->pushHandler( new DatabaseLoggingHandler( $this->db,
		                                                       $this->conf->get( 'logging', 'audit_level' ) ) );
	}

	/**
	 * Initializes the Vault service object.
	 */
	protected function init_service() {
		$this->service = new Service( $this->conf,
		                              $this->repo,
		                              $this->audit,
		                              $this->views,
		                              new Mailer_Factory( $this->conf,
		                                                  $this->log ) );
	}

	/**
	 * Bootstraps the application.
	 */
	protected function bootstrap() {
		$this->init_basic_logging();
		$this->load_config();
		$this->init_database();
		$this->init_service();
	}
}
