<?php
const USER_AGENT = "Mozilla/5.0 (compatible; rtdownloader/0.1 PHP7 duncan_bristow@candunc.com)";

require_once "config.php";

class Database {
	public $conn;

	public function __construct() {
		$this->conn = new mysqli(DB_ADDR, DB_USER, DB_PASS, "roosterteeth");

		if ($this->conn->connect_error) {
			die("Connection failed: " . $this->conn->connect_error);
		}

		$this->conn->query("CREATE TABLE IF NOT EXISTS roosterteeth.login (
				`id` TINYINT NOT NULL,
				`access_token` VARCHAR(100) NOT NULL,
				`expiry` TIMESTAMP NOT NULL,
				UNIQUE INDEX `Index 1` (`id`)
			)
			COLLATE='utf8mb4_unicode_ci'
			;");

		$this->conn->query("INSERT IGNORE INTO roosterteeth.login (id, access_token, expiry) VALUES (1, '', 0);");

		$this->conn->query("CREATE TABLE IF NOT EXISTS roosterteeth.m3u8 (
				`slug` VARCHAR(200) NOT NULL,
				`base` VARCHAR(500) NOT NULL,
				`1080` VARCHAR(200),
				`720` VARCHAR(200),
				`480` VARCHAR(200),
				UNIQUE INDEX `Index 1` (`slug`)
			)
			COLLATE='utf8mb4_unicode_ci'
			;");
	}

	public function __destruct() {
		$this->conn->close();
	}

	public function getSingleRow($query) {
		$result = $this->conn->query($query);
		return $result->fetch_assoc();
	}

	public function getManyRows($query) {
		$stack = array();
		$result = $this->conn->query($query);

		while($row = $result->fetch_assoc()) {
			array_push($stack,$row);
		}
		return $stack;
	}

	public function prepare($query) {
		return $this->conn->prepare($query);
	}
}

# https://stackoverflow.com/q/4372710/1687505
function get($url, $header = NULL) {
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => false,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_ENCODING       => "",
		CURLOPT_USERAGENT      => USER_AGENT,
		CURLOPT_AUTOREFERER    => true,
		CURLOPT_CONNECTTIMEOUT => 120,
		CURLOPT_TIMEOUT        => 120,
		CURLOPT_MAXREDIRS      => 10
	);

	# Basically an edge case to allow curl with an authentication header.
	if (isset($header) && !is_null($header)) {
		$options[CURLOPT_HTTPHEADER] = $header;
	}

	$ch = curl_init($url);
	curl_setopt_array($ch, $options);
	$content = curl_exec($ch);
	curl_close($ch);

	return $content;
}

function post($url, $data) {
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => false,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_ENCODING       => "",
		CURLOPT_USERAGENT      => USER_AGENT,
		CURLOPT_AUTOREFERER    => true,
		CURLOPT_CONNECTTIMEOUT => 120,
		CURLOPT_TIMEOUT        => 120,
		CURLOPT_MAXREDIRS      => 10,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS     => $data
	);

	$ch = curl_init($url);
	curl_setopt_array($ch, $options);
	$response = curl_exec($ch);
	curl_close($ch);

	return $response;
}

function parse_m3u8($input) {
	# Break on all types of newlines 
	# https://stackoverflow.com/a/11165332
	$lines = preg_split("/\r\n|\n|\r/", $input);

	$count = 0;
	$output = array();

	foreach ($lines as $line) {
		// Skip first line if present
		if ($line != "#EXTM3U") {
			// Check if first character is #
			if (substr($line, 0, 1) == "#") {

				// Metadata is comma seperated, key=value style.
				$output[$count] = array();
				$elements = quoted_explode(",", substr($line, 1), '"');
				foreach ($elements as $element) {
					$kv = explode("=", $element);
					$output[$count][$kv[0]] = $kv[1];
				}
			} else {
				$output[$count]["URL"] = $line;
				$count += 1;
			}
		}
	}

	return $output;
}

# Credits to Mattihieu Riegler
# https://stackoverflow.com/a/19366999/1687505
function utf8ize($d) {
	if (is_array($d)) {
		foreach ($d as $k => $v) {
			$d[$k] = utf8ize($v);
		}
	} elseif (is_string ($d)) {
		return utf8_encode($d);
	}
	return $d;
}

# Credits to Brilliand
# https://stackoverflow.com/a/13755505
function quoted_explode($delimiter = ',', $subject, $quote = '\'') {
	$regex = "(?:[^$delimiter$quote]|[$quote][^$quote]*[$quote])+";
	preg_match_all('/'.str_replace('/', '\\/', $regex).'/', $subject, $matches);
	return $matches[0];
}
