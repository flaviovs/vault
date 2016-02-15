<?php

namespace Vault;

class View {
	protected $data = [];
	protected $path;

	public function __construct( $path ) {
		$this->path = $path;
	}

	public function set( $var, $value ) {
		$this->data[ $var ] = $value;
	}

	public function __toString() {
		ob_start();

		// "Trigger" a "-" error, so that we can check if the include
		// issued any error. Unfortunately error_clear_last() requires
		// PHP >= 7.
		@trigger_error('-');

		extract($this->data);
		@include $this->path;
		$err = error_get_last();
		$output = ob_get_clean();

		if ($err['message'] != '-') {
			// Unfortunately we do not have a Logger here, nor can we
			// throw exceptions inside __toString(), so call
			// error_log() as a last resort.
			error_log($err['message']);
		}

		return $output;
	}
}


class View_Registry {
	const PATH = VAULT_ROOT . "/view";

	public function get( $name ) {
		$path = static::PATH . "/$name.php";
		if ( ! file_exists($path) ) {
			throw new \RuntimeException("No such view '$name'");
		}
		return new View($path);
	}
}
