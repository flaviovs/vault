<?php

namespace Vault;

require __DIR__ . '/../vendor/autoload.php';

function main() {
	$app = new Installer_App('install');
	$res = $app->run();
	return $res == 200 ? 0 : intval( $res / 100 );
}

exit(main());
