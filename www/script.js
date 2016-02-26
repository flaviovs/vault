(function ( w, d ) {

	d.getElementsByTagName( 'html' )[ 0 ].setAttribute( 'class', 'js' );

	if ( typeof Vault.config.refresh == 'number' ) {
		setTimeout( d.location.reload, Vault.config.refresh * 1000 );
	}

})( window, document );
