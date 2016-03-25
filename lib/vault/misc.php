<?php

namespace Vault;

class DatabaseLoggingHandler extends \Monolog\Handler\AbstractProcessingHandler {
	protected $db;
	protected $sth;

	public function __construct( \PDO $db,
	                             $level = \Monolog\Logger::DEBUG,
	                             $bubble = true ) {
		parent::__construct( $level, $bubble );
		$this->db = $db;
	}

	protected function get_level_id( $level_name ) {
		return 'EMERGENCY' == $level_name ? '!' : $level_name[0];
	}

	public function write( array $record ) {
		if ( ! $this->sth ) {
			$this->sth = $this->db->prepare( 'INSERT INTO vault_log (created, vault_log_level_id, message, vault_app_id) VALUES (NOW(), ?, ?, ?)' );
		}

		if ( isset( $extra['app'] ) ) {
			$appid = $extra['app']->appid;
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


class Mailer extends \PHPMailer {
	protected $debug;
	protected $log;

	public function __construct( \UConfig\Config $conf, \Monolog\Logger $log ) {
		parent::__construct( true ); // Tell PHPMailer that we want exceptions.

		try {
			$from_address = $conf->get( 'mailer', 'from_address' );
		} catch ( \UConfig\Exception $ex ) {
			throw new VaultException( 'Missing from_address mailer configuration' );
		}

		try {
			$from_name = $conf->get( 'mailer', 'from_name' );
		} catch ( \UConfig\Exception $ex ) {
			throw new VaultException( 'Missing from_name mailer configuration' );
		}

		try {
			$this->debug = $conf->get( 'debug', 'mailer' );
		} catch ( \UConfig\Exception $ex ) {
			$this->debug = FALSE;
		}

		if ( $this->debug ) {
			// @codingStandardsIgnoreStart
			$this->Mailer = 'debug';
			// @codingStandardsIgnoreEnd
		}

		$this->setFrom( $from_address, $from_name );

		$this->log = $log;
	}

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


class Esc {

	static public function html( $string, $quote_style = ENT_NOQUOTES ) {
		return htmlspecialchars( $string, $quote_style | ENT_HTML5, 'UTF-8' );
	}

	static public function attr( $string ) {
		return htmlspecialchars( $string, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	static public function instructions( $string ) {
		return strip_tags( $string, Request::INSTRUCTIONS_ALLOWED_TAGS );
	}
}


class Message_Area {
	const INFO = 0;
	const ERROR = 1;

	protected $messages = [
		Message_Area::INFO => [],
		Message_Area::ERROR => [],
	];

	public function add_message( $level, $msg ) {
		$this->messages[ $level ][] = $msg;
	}

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


class Valid {
	static public function email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}
