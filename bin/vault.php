<?php

namespace Vault;

require __DIR__ . '/../vendor/autoload.php';

function main() {
	$app = new CLI_App('vault');
	return $app->run();
}

exit(main());
