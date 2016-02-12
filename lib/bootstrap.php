<?php

namespace Vault;

define( 'VAULT_ROOT', dirname( __DIR__ ) );

/**
 * Our error handler
 */
function error_handler( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity )
		throw new \ErrorException( $message, 0, $severity, $file, $line );
}

set_error_handler( __NAMESPACE__ . '\\error_handler' );
