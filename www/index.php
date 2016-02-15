<?php

namespace Vault;

require __DIR__ . '/../vendor/autoload.php';

function main() {
	$app = new Front_End_App('www');
	$app->run();
}

main();
