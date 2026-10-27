<?php

namespace Controls {
	function checkbox_tag($name, $checked) {
		return '<input type="checkbox" name="' . $name . '"' . ($checked ? ' checked' : '') . '>';
	}

	function input_tag($name, $value, $type, $attributes) {
		return '<input name="' . $name . '" value="' . $value . '">';
	}
}

namespace {
	error_reporting(E_ALL);
	set_error_handler(function ($severity, $message, $file, $line) {
		throw new \ErrorException($message, 0, $severity, $file, $line);
	});

	class Plugin {
		public $pdo;
	}

	class PluginHost {
		const HOOK_FILTER_TRIGGERED = 1;
		const HOOK_PREFS_TAB = 2;
		const HOOK_PREFS_EDIT_FEED = 3;
		const HOOK_PREFS_SAVE_FEED = 4;

		public array $settings = [
			'server' => 'invalid-test-server',
			'app_token' => 'test-only',
			'priority' => '4',
			'enabled_feeds' => [10, 20],
			'app_tokens' => [],
			'feed_priorities' => [10 => '4', 20 => '4'],
		];

		function get($plugin, $key) {
			return $this->settings[$key] ?? false;
		}

		function set($plugin, $key, $value) {
			$this->settings[$key] = $value;
		}

		function add_hook($hook, $plugin) {}
		function add_filter_action($plugin, $name, $label) {}
	}

	class Debug {
		const LOG_VERBOSE = 1;
		public static array $messages = [];

		static function log($message, $level = 0) {
			self::$messages[] = $message;
		}
	}

	class Logger {
		public static array $messages = [];

		static function log($level, $message) {
			self::$messages[] = $message;
		}
	}

	class Feeds {
		static function _get_title($feed, $owner) {
			return 'Test feed';
		}
	}

	class ORM {
		public string $title = 'Test feed';

		static function for_table($table) {
			return new self();
		}

		function select($column) {
			return $this;
		}

		function find_one($id) {
			return $this;
		}
	}

	class TestDatabase {
		function prepare($sql) {
			return new TestStatement();
		}
	}

	class TestStatement {
		function execute($params) {}
		function fetch() {
			return false;
		}
	}

	function checkbox_to_sql_bool($value) {
		return $value === 'on' || $value === '1';
	}

	require __DIR__ . '/../plugins.local/gotify_notifications/init.php';

	$checks = 0;
	function check(bool $condition, string $message): void {
		global $checks;
		if (!$condition) {
			throw new \RuntimeException($message);
		}
		$checks++;
	}

	function saveFeed($plugin, $snooze = null, $enabled = true): void {
		$_POST = ['gotify_token' => '', 'gotify_priority' => '4'];
		if ($enabled) {
			$_POST['gotify_enabled'] = 'on';
		}
		if ($snooze !== null) {
			$_POST['gotify_snooze'] = $snooze;
		}
		$plugin->hook_prefs_save_feed(10);
	}

	function checkDelivery($plugin, int $feed, bool $expected, array $actions = []): void {
		$article = [
			'feed' => ['id' => $feed],
			'title' => 'Test article',
			'link' => 'https://example.invalid/article',
			'guid_hashed' => 'new-test-article',
		];
		foreach (['feed', 'filter'] as $path) {
			Debug::$messages = [];
			if ($path === 'feed') {
				$plugin->hook_filter_triggered($feed, 1, $article, [], [], $actions);
			} else {
				$plugin->hook_article_filter_action($article, []);
			}
			// Invalid test server stops delivery before any HTTP request is made.
			$attempted = in_array('[Gotify] Sending message via invalid-test-server', Debug::$messages, true);
			check($attempted === $expected, "{$path} delivery for feed {$feed}");
		}
	}

	$host = new PluginHost();
	$plugin = new gotify_notifications();
	$plugin->pdo = new TestDatabase();
	$plugin->init($host);

	checkDelivery($plugin, 10, true);
	foreach ([1, 2, 7, 14, 30] as $days) {
		$before = time();
		saveFeed($plugin, (string) $days);
		$until = $host->settings['feed_snoozes'][10];
		check($until >= $before + $days * 86400 && $until <= time() + $days * 86400,
			"Exact {$days}-day expiry");
		check($host->settings['enabled_feeds'] === [10, 20], 'Snooze preserves enable settings');
		checkDelivery($plugin, 10, false);
		checkDelivery($plugin, 20, true);
	}

	$until = $host->settings['feed_snoozes'][10];
	saveFeed($plugin, 'keep');
	check($host->settings['feed_snoozes'][10] === $until, 'Keep preserves expiry');
	saveFeed($plugin);
	check($host->settings['feed_snoozes'][10] === $until, 'Missing field preserves expiry');

	ob_start();
	$plugin->hook_prefs_edit_feed(10);
	$html = ob_get_clean();
	foreach (['keep', 'resume', '1', '2', '7', '14', '30'] as $option) {
		check(str_contains($html, 'value="' . $option . '"'), "UI contains {$option}");
	}
	check(str_contains($html, 'name="gotify_snooze"'), 'UI submits snooze selection');
	check(str_contains($html, 'value="keep" selected="selected"'), 'UI defaults to keep');
	check(str_contains($html, gmdate('Y-m-d H:i:s', $until) . ' UTC'), 'UI shows exact expiry');

	$host->settings['feed_snoozes'][20] = time() + 86400;
	saveFeed($plugin, 'resume');
	check(!isset($host->settings['feed_snoozes'][10]), 'Resume clears expiry');
	check(isset($host->settings['feed_snoozes'][20]), 'Resume preserves other snoozes');
	checkDelivery($plugin, 10, true);
	checkDelivery($plugin, 20, false);

	foreach ([time() - 1, time()] as $expired) {
		$host->settings['feed_snoozes'][10] = $expired;
		checkDelivery($plugin, 10, true);
	}
	ob_start();
	$plugin->hook_prefs_edit_feed(10);
	check(str_contains(ob_get_clean(), 'Notifications are not snoozed.'), 'UI shows expiry has passed');

	saveFeed($plugin, '2');
	$host->settings['feed_snoozes'][10] = time() + 10;
	saveFeed($plugin, '2');
	check($host->settings['feed_snoozes'][10] >= time() + 2 * 86400, 'Resnooze restarts duration');

	foreach (['0', '-1', '3', '31', '1.0', 'garbage', ['1']] as $invalid) {
		$previous = $host->settings;
		try {
			saveFeed($plugin, $invalid);
			throw new \RuntimeException('Invalid duration accepted');
		} catch (\InvalidArgumentException $error) {
			check($error->getMessage() === 'Invalid Gotify snooze duration.', 'Explicit validation error');
		}
		check($host->settings === $previous, 'Invalid duration does not mutate settings');
		check(end(Logger::$messages) === 'Invalid Gotify snooze duration.', 'Invalid duration is logged');
	}

	saveFeed($plugin, '1', false);
	$updaterPlugin = new gotify_notifications();
	$updaterPlugin->pdo = new TestDatabase();
	$updaterPlugin->init($host);
	checkDelivery($updaterPlugin, 10, false);
	saveFeed($plugin, 'resume', false);
	check(!in_array(10, $host->settings['enabled_feeds']), 'Resume does not enable disabled feed');
	Debug::$messages = [];
	$plugin->hook_filter_triggered(10, 1, ['feed' => ['id' => 10]], [], [], []);
	check(in_array('[Gotify] Gotify not enabled for this feed.', Debug::$messages, true),
		'Disabled feed remains suppressed');
	Debug::$messages = [];
	$plugin->hook_article_filter_action([
		'feed' => ['id' => 10],
		'title' => 'Test article',
		'link' => 'https://example.invalid/article',
	], []);
	check(in_array('[Gotify] Sending message via invalid-test-server', Debug::$messages, true),
		'Explicit filter action still works when feed notifications are disabled');
	$host->settings['enabled_feeds'][] = 10;
	foreach (['filter', 'catchup'] as $type) {
		Debug::$messages = [];
		$plugin->hook_filter_triggered(10, 1, [
			'feed' => ['id' => 10],
			'guid_hashed' => 'test',
		], [], [], [['type' => $type]]);
		check(!in_array('[Gotify] Sending message via invalid-test-server', Debug::$messages, true),
			"Existing {$type} suppression preserved");
	}

	echo "Passed {$checks} Gotify snooze checks.\n";
}
