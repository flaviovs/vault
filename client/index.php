<?php

namespace Vault;

require __DIR__ . '/../vendor/autoload.php';

function main() {
	$app = new ClientApp( 'vault-client' );
	$app->run();
}

main();
