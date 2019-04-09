<?php
require_once "./common.php";

# https://stackoverflow.com/a/768472/1687505
function redirect($url, $statusCode = 303)
{
	header('Location: ' . $url, true, $statusCode);
	die();
}

# Adds UTF8-ization on failure to encode. Replaces json_encode()
function to_json($input) {
	$output = json_encode($input);
	if (json_last_error() == JSON_ERROR_NONE) {
		return $output;
	} else {
		# Apparently valid utf8mb4 strings occasionally break json_encode.
		return json_encode(utf8ize($input));
	}
}

// TODO: Restrict based on release date.
function get_m3u8($slug) {
	$login_info = array(
		"client_id" => "4338d2b4bdc8db1239360f28e72f0d9ddb1fd01e7a38fbb07b4b1f4ba4564cc5",
		"grant_type" => "password",
		"scope" => "user public",
		"username" => RT_USER,
		"password" => RT_PASS
	);
	$db = new Database();

	$login_status = $db->getSingleRow("SELECT access_token, expiry FROM roosterteeth.login");
	if ($login_status["expiry"] < time()) {
		$login = json_decode(post("https://auth.roosterteeth.com/oauth/token",json_encode($login_info)), true);
		$stmt = $db->prepare("UPDATE roosterteeth.login SET access_token = ?, expiry = ?");

		$expiry = $login["created_at"]+$login["expires_in"];
		$stmt->bind_param('si',$login["access_token"],$expiry);
		$stmt->execute();
		$access_token = $login["access_token"];
	} else {
		$access_token = $login_status["access_token"];
	}

	$stmt = $db->prepare("SELECT `base`, `1080`, `720`, `480` FROM roosterteeth.m3u8 WHERE slug = ?");
	$stmt->bind_param('s', $slug);
	$stmt->execute();

	$result = $stmt->get_result();
	if ($result->num_rows === 0) {
		$tmp = get("https://svod-be.roosterteeth.com/api/v1/episodes/" . $slug . "/videos",array("authorization: Bearer " . $access_token));

		$data = json_decode($tmp, true);

		$meta_url = $data["data"][0]["attributes"]["url"];
		$base = substr($meta_url, 0, strrpos($meta_url, '/')) . '/';

		$values = array();
		$streams = parse_m3u8(get($meta_url));
		foreach ($streams as $stream) {
			if (isset($stream["RESOLUTION"])) {
				switch ($stream["RESOLUTION"]) {
					case "1920x1080":
						$values["1080"] = $stream["URL"];
						break;
					case "1280x720":
						$values["720"] = $stream["URL"];
						break;
					case "854x480":
						$values["480"] = $stream["URL"];
						break;
				}
			}
		}

		$stmt = $db->prepare("INSERT INTO roosterteeth.m3u8 (`slug`, `base`, `1080`, `720`, `480`) VALUES (?, ?, ?, ?, ?)");

		$stmt->bind_param('sssss', $slug, $base, $values["1080"], $values["720"], $values["480"]);
		$stmt->execute();

		return ($base . $values["1080"]);
	} else {
		$row = $result->fetch_assoc();
		return ($row["base"] . $row["1080"]);
	}
}

function add_header() {
	# https://gist.github.com/mironal/2939462
	header("Content-type: application/json; charset=utf-8");
}

# Todo: Turn errors into valid JSON

if (isset($_GET["action"])) {
	$action = $_GET["action"];

	if ($action === "m3u8") {
		if (isset($_GET["slug"])) {
			echo(get_m3u8($_GET["slug"]));
		} else {
			die("slug not set.");
		}

	} elseif ($action === "getEpisodes") {
		add_header();
		$db = new Database();
		if (isset($_GET["channel"])) {
			$sql = "SELECT slug, title, channel, canonical_link, image, show_title, show_slug FROM roosterteeth.episodes WHERE channel=? ORDER BY `release_sponsor` DESC LIMIT 30";
			$stmt = $db->prepare($sql);
			$stmt->bind_param('s', $_GET["channel"]);
			$stmt->execute();

			$result = $stmt->get_result();
			if ($result->num_rows === 0) {
				die("Invalid channel '" . $_GET["channel"] . "'");
			}

			$stack = array();
			while($row = $result->fetch_assoc()) {
				array_push($stack,$row);
			}
		} else {
			$sql = "SELECT slug, title, channel, canonical_link, image, show_title, show_slug FROM roosterteeth.episodes ORDER BY `release_sponsor` DESC LIMIT 30";

			$stack = $db->getManyRows($sql);
		}

		echo(to_json($stack));

	} elseif ($action === "getShows") {
		add_header();
		$db = new Database();
		
		if (isset($_GET["channel"])) {
			die("Unimplemented branch");

		} else {
			$sql = "SELECT title, channel, canonical_link, image FROM roosterteeth.shows ORDER BY `date` DESC LIMIT 30";
			echo(to_json($db->getManyRows($sql)));
		}
	} elseif ($action === "tv_api") {
		add_header();
		$db = new Database();
		$sql = "SELECT slug, title, channel, canonical_link, image, show_title, show_slug FROM roosterteeth.episodes WHERE channel=? ORDER BY `date` DESC, `id` DESC LIMIT 6 OFFSET ?";

		$page = ($_GET['page']-1)*6;

		$stmt = $db->prepare($sql);
		$stmt->bind_param('ss', $_GET['channel'], $page);
		$stmt->execute();

		$result = $stmt->get_result();
		$stack = array();
		while($row = $result->fetch_assoc()) {
			array_push($stack,$row);
		}

		echo(to_json($stack));
	} else {
		die("Unknown action '" . $action . "'");
	}
} else {
	die("action not set.");
}
