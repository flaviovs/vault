<?php

namespace Vault;

require __DIR__ . '/../vendor/autoload.php';

function main() {
	$app = new CLI_App();
	return $app->run();
}

exit(main());
