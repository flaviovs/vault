<?php

namespace Vault;

class ConfigException extends \Exception {}
class ConfigSectionNotFoundException extends ConfigException {}
class ConfigKeyNotFoundException extends ConfigException {}

class Config {
	protected $conf = [];

	public function __construct($file = NULL) {
		if ( $file ) {
			$this->load_file( $file );
		}
	}

	public function load_file( $file ) {
		$this->conf = array_replace_recursive( $this->conf,
		                                       parse_ini_file( $file, TRUE ) );
	}

	public function get( $section, $key, $default = NULL ) {
		if ( ! array_key_exists( $section, $this->conf ) ) {
			if ( $default !== NULL ) {
				return $default;
			}
			throw new ConfigSectionNotFound( $section );
		}

		if ( ! array_key_exists( $key, $this->conf[ $section ] ) ) {
			if ( $default !== NULL ) {
				return $default;
			}
			throw new ConfigKeyNotFound( $key );
		}

		return $this->conf[ $section ][ $key ];
	}
}
