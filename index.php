<?php

namespace app;

require('./discord_hook.php');

if (php_sapi_name() === 'cli') {
	// CLI entry point
	if (count($argv) < 2 || empty($argv[1])) {
		print('Run again with webhook token as first argument');
		exit(1);
	}
	DiscordHook::run($argv[1]);
} else {
	// HTTP entry point
	if (!isset($_GET['webhook']) || empty($_GET['webhook'])) {
		http_response_code(400);
		exit('Missing argument webhook');
	}

	DiscordHook::run($_GET['webhook']);
}
