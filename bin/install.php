<?php

namespace Vault;

require __DIR__ . '/../vendor/autoload.php';

function main() {
	$app = new Installer_App( 'install' );
	return $app->run();
}

exit( main() );
