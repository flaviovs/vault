<?php

namespace Vault;

require __DIR__ . '/../vendor/autoload.php';

function main() {
	$app = new REST_App( 'api' );
	$app->run();
}

main();
