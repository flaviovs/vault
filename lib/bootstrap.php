<?php
/**
 * Global contants, functions, and initialization code.
 */

namespace Vault;

/**
 * The Vault package root directory.
 */
define( 'VAULT_ROOT', dirname( __DIR__ ) );

/**
 * Stub translation procedure.
 *
 * @param string $text The text that should be translated.
 */
function __( $text ) {
	return $text;
}

/**
 * Our error handler.
 *
 * @throws \ErrorException in case of errors.
 */
function error_handler( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) {
		throw new \ErrorException( $message, 0, $severity, $file, $line );
	}
}

/*
 * Global initialization code follows.
 */

error_reporting( E_ALL );

set_error_handler( __NAMESPACE__ . '\\error_handler' );
