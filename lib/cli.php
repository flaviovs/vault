<?php

namespace Vault;

class CLI_App extends Vault_App {

	protected function init_basic_logging() {
		$handler = new \Monolog\Handler\StreamHandler('php://stderr');
		$handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"%channel%: %level_name%: %message% %context% %extra%\n"));
		$this->log->setHandlers([$handler]);
	}

	public function run() {
		// Do nothing
	}
}
