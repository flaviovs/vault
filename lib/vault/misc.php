<?php
/**
 * Miscellaneous helper classes.
 */

namespace Vault;

/**
 * A Monolog logging handler designed to log to the Vault audit log.
 */
class DatabaseLoggingHandler extends \Monolog\Handler\AbstractProcessingHandler {
	/**
	 * The database connection object.
	 *
	 * This is a PDO-compatible object.
	 *
	 * @var \Aura\Sql\ExtendedPDO
	 */
	protected $db;

	/**
	 * The prepared logging statement.
	 *
	 * @var PDOStatement
	 */
	protected $sth;

	/**
	 * Constructs the object.
	 */
	public function __construct( \PDO $db,
	                             $level = \Monolog\Logger::DEBUG,
	                             $bubble = true ) {
		parent::__construct( $level, $bubble );
		$this->db = $db;
	}

	/**
	 * Map a Monolog level name to our database level id.
	 *
	 * @param string $level_name The log level name.
	 */
	protected function get_level_id( $level_name ) {
		return 'EMERGENCY' == $level_name ? '!' : $level_name[0];
	}

	/**
	 * Write the log record.
	 *
	 * @param array $record The Monolog log record.
	 */
	public function write( array $record ) {
		if ( ! $this->sth ) {
			$this->sth = $this->db->prepare( 'INSERT INTO vault_log (created, vault_log_level_id, message, vault_app_id) VALUES (NOW(), ?, ?, ?)' );
		}

		if ( isset( $record['context']['app'] ) ) {
			$appid = $record['context']['app']->appid;
		} else {
			$appid = null;
		}

		$this->sth->execute( [
			                     $this->get_level_id( $record['level_name'] ),
			                     $record['message'],
			                     $appid,
		                     ] );
	}
}

/**
 * A PHP-Mailer class with debugging capabilities.
 *
 * To enable debugging, set the internal mailer to 'debug'. For
 * example:
 *
 *      $mailer = new Mailer( $logger );
 *      $mailer->Mailer = 'debug';
 */
class Mailer extends \PHPMailer {

	/**
	 * The logger object that the object should write debugging info to.
	 *
	 * @var \Monolog\Logger
	 */
	protected $log;

	/**
	 * Constructs the object.
	 *
	 * @param \Monolog\Logger $log The logger object.
	 */
	public function __construct( \Monolog\Logger $log ) {
		parent::__construct( true ); // Tell PHPMailer that we want exceptions.
		$this->log = $log;
	}

	/**
	 * A PHP-Mailer-compatible 'debug' mailer.
	 */
	protected function debugSend( $headers, $body ) {
		$this->log->addDebug( 'Omitting email to '
		                      . implode( ',',
		                                 array_keys( $this->all_recipients ) ),
		                      [
			                      'headers' => $headers,
			                      'body' => $body,
		                      ] );
		return true;
	}
}

/**
 * A mailer factory object.
 */
class Mailer_Factory {

	/**
	 * The logger object to be used by created mailers.
	 *
	 * @var Monolog\Logger
	 */
	protected $log;

	/**
	 * The configured sender (human-readable) name that created
	 * mailers should use.
	 *
	 * @var string
	 */
	protected $from_name;

	/**
	 * The configured sendes address that created mailers should use.
	 *
	 * @var string
	 */
	protected $from_address;

	/**
	 * The debug flag.
	 *
	 * @var bool
	 */
	protected $debug;

	/**
	 * Constructs the object.
	 *
	 * @param \UConfig\Config $conf A configuration object.
	 * @param \Monolog\Logger $log  A logger object.
	 *
	 * @throws VaultException if the factory object cannot be properly
	 * initialized.
	 */
	public function __construct( \UConfig\Config $conf, \Monolog\Logger $log ) {
		$this->log = $log;

		try {
			$this->from_name = $conf->get( 'mailer', 'from_name' );
		} catch ( \UConfig\Exception $ex ) {
			throw new VaultException( 'Missing from_name mailer configuration' );
		}

		try {
			$this->from_address = $conf->get( 'mailer', 'from_address' );
		} catch ( \UConfig\Exception $ex ) {
			throw new VaultException( 'Missing from_address mailer configuration' );
		}

		try {
			$this->debug = $conf->get( 'debug', 'mailer' );
		} catch ( \UConfig\Exception $ex ) {
			$this->debug = false;
		}
	}

	/**
	 * Creates a new mailer object.
	 *
	 * @return Mailer A new mailer object.
	 */
	public function new_mailer() {
		$mailer = new Mailer( $this->log );

		$mailer->setFrom( $this->from_address, $this->from_name );

		if ( $this->debug ) {
			// @codingStandardsIgnoreStart
			$mailer->Mailer = 'debug';
			// @codingStandardsIgnoreEnd
		}

		return $mailer;
	}
}

/**
 * Static class with several escaping methods.
 */
class Esc {

	/**
	 * Escapes HTML.
	 *
	 * @param string $string The HTML string to be escaped.
	 * @param int    $quote_style Escaping flags.
	 *
	 * @see htmlspecialchars()
	 */
	static public function html( $string, $quote_style = ENT_NOQUOTES ) {
		return htmlspecialchars( $string, $quote_style | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Escapes HTML attributes.
	 *
	 * @param string $string The string to be escaped.
	 */
	static public function attr( $string ) {
		return htmlspecialchars( $string, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Escapes request instructions.
	 *
	 * @param string $string The instructions.
	 */
	static public function instructions( $string ) {
		$html = \Michelf\Markdown::defaultTransform( Esc::html( $string ) );
		return strip_tags( $html, '<em><strong><code>' );
	}
}

/**
 * A class implementing a HTML-based "message board"-like structure.
 */
class Message_Area {
	const INFO = 0;
	const ERROR = 1;

	/**
	 * The message storage array.
	 *
	 * First level contains the message types (INFO or ERROR), and
	 * second level the actual messages.
	 *
	 * @var array
	 */
	protected $messages = [
		Message_Area::INFO => [],
		Message_Area::ERROR => [],
	];

	/**
	 * Adds a message.
	 *
	 * @param int    $level The message level (static::INFO or static::ERROR).
	 * @param string $msg   The message.
	 */
	public function add_message( $level, $msg ) {
		$this->messages[ $level ][] = $msg;
	}

	/**
	 * Returns all messages that were added at a level.
	 *
	 * @return string HTML list containing all messages at the
	 * specified level. If only one message was added, just return it
	 * (i.e., do not build a HTML list). If no messages were added,
	 * returns an empty string.
	 *
	 * @param int $level The message level (static::INFO or static::ERROR).
	 */
	public function get_message_list( $level ) {
		switch ( count( $this->messages[ $level ] ) ) {
			case 0:
				return '';

			case 1:
				return $this->messages[ $level ][0];
		}

		$list = "<ul>\n";
		foreach ( $this->messages[ $level ] as $msg ) {
			$list .= "<li>$msg</li>\n";
		}
		$list .= "</ul>\n";

		return $list;
	}

	/**
	 * Returns an HTML string representing the contents of this
	 * message area.
	 */
	public function __toString() {
		$info = $this->get_message_list( static::INFO );
		$error = $this->get_message_list( static::ERROR );

		$out = '';

		if ( $info ) {
			$out .= '<div class="info">' . $info . "</div>\n";
		}

		if ( $error ) {
			$out .= '<div class="error">' . $error . "</div>\n";
		}

		return $out;
	}
}

/**
 * A static class containing validation functions.
 */
class Valid {
	/**
	 * Validates that an e-mail address is valid.
	 *
	 * @param string $email The address to validate.
	 */
	static public function email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}
