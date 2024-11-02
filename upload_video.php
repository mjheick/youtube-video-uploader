#!/usr/bin/php
<?php
/* Create project API key from https://console.cloud.google.com/apis/credentials?project=<project name>, save it as api_key.txt */
if (!file_exists('api_key.txt')) {
	die("api_key.txt missing. create one and save it as api_key.txt\n");
}
$apikey = trim(file_get_contents('api_key.txt'));
if (strlen($apikey) == 0) {
	die("api_key.txt is empty or bad. make sure it exists as the only data in the file\n");
}

$o = getopt('c:f:t:p:d:', ['code:', 'file:', 'title:', 'priv:', 'desc:']);
function print_help() {
	$help = [
		'Here is the help:',
		'--code  the oauth2 code provided by google to authorize this script',
		'--file  The video to upload',
		'--title The title to give the video. (optional)',
		'--priv  The privacy to publish the video at [public, private(default), unlisted]',
		'--desc  The description to add to the video (optional)',
	];
	echo implode("\n", $help);
	echo "\n";
}

/* client_secrets.json, bread and butter */
if (!file_exists('client_secrets.json')) {
	die("client_secrets.json missing\n");
}
$client_secrets = json_decode(file_get_contents('client_secrets.json'), true);
if (!isset($client_secrets['web'])) { die("client_secrets.json, no web key\n"); }
if (!isset($client_secrets['web']['auth_uri'])) { die("client_secrets.json, no auth_uri key\n"); }
if (!isset($client_secrets['web']['token_uri'])) { die("client_secrets.json, no token_uri key\n"); }
if (!isset($client_secrets['web']['client_id'])) { die("client_secrets.json, no client_id key\n"); }
if (!isset($client_secrets['web']['client_secret'])) { die("client_secrets.json, no client_secret key\n"); }
if (!isset($client_secrets['web']['redirect_uris'])) { die("client_secrets.json, no redirect_uris key\n"); }
if (count($client_secrets['web']['redirect_uris']) == 0) { die("client_secrets.json, redirect_uris are empty\n"); }

/* If we're passing in a code we need to authorize the app */
if (isset($o['code'])) {
	/* We have a code, lets throw it to google */
	echo "I have code: " . $o['code'] . "\n";
	$params = http_build_query([
		'client_id' => $client_secrets['web']['client_id'],
		'client_secret' => $client_secrets['web']['client_secret'],
		'code' => $o['code'],
		'grant_type' => 'authorization_code',
		'redirect_uri' => $client_secrets['web']['redirect_uris'][0]
	]);
	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL => $client_secrets['web']['token_uri'],
		CURLOPT_POST => true,
		CURLOPT_HEADER => false,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS => $params,
	]);
	$data = curl_exec($ch);
	file_put_contents('oauth.json', $data);
	curl_close($ch);
	die("I've written the oauth.json file. Uploading should be able to commence\n");
}

/* oauth.json, the stuff we need to upload. authenticate if we don't have the file */
if (!file_exists('oauth.json')) {
	$params = http_build_query([
		'scope' => 'https://www.googleapis.com/auth/youtube.upload',
		'redirect_uri' => $client_secrets['web']['redirect_uris'][0],
		'response_type' => 'code',
		'client_id' => $client_secrets['web']['client_id'],
		'access_type' => 'offline',
	]);
	$url = $client_secrets['web']['auth_uri'] . '?' . $params;
	die("Go to the following URL:\n\n$url\n\nOnce you've authorized provide the code here as\nupload_video.php --code \"code\"\n");
}

/* Load up our file. Do maintenance */
$oauth = json_decode(file_get_contents('oauth.json'), true);
if (isset($oauth['error'])) {
	echo $oauth['error'];
	if (isset($oauth['error_description'])) {
		echo ': ' . $oauth['error_description'];
	}
	unlink('oauth.json');
	die("\n");
}
/* Our custom field of when we got this access */
if (!isset($oauth['issued'])) {
	$oauth['issued'] = time();
	$oauth['expires'] = intval($oauth['issued']) + intval($oauth['expires_in']);
	/* Write these */
	file_put_contents('oauth.json', json_encode($oauth));
}
/* Check to see if we need to run the refresh token flow */
if ($oauth['expires'] < time()) {
	echo "token expired. renewing...";
	/* Must refresh our token */
	$params = http_build_query([
		'client_id' => $client_secrets['web']['client_id'],
		'client_secret' => $client_secrets['web']['client_secret'],
		'grant_type' => 'refresh_token',
		'refresh_token' => $oauth['refresh_token'],
	]);
	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL => $client_secrets['web']['token_uri'],
		CURLOPT_POST => true,
		CURLOPT_HEADER => false,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS => $params,
	]);
	$data = curl_exec($ch);
	/* Check to make sure we got everything */
	$refresh = json_decode($data, true);
	if (!(isset($refresh['access_token']) || isset($refresh['expires_in']))) {
		die("error refreshing access token: " . var_export($refresh, true) . "\n");
	}
	$oauth['issued'] = time();
	$oauth['expires'] = intval($oauth['issued']) + intval($refresh['expires_in']);
	$oauth['access_token'] = $refresh['access_token'];
	file_put_contents('oauth.json', json_encode($oauth));
	curl_close($ch);
	echo "ok\n";
}

/* At this point we've done everything oauth-related and have a good access token */
if (!isset($o['file'])) {
	print_help();
	die("\n--file not provided.\n");
}
if (!file_exists($o['file'])) {
	print_help();
	die("\nfile " . $o['file'] . " not found.\n");
}

/* Lets get this file uploaded and start taking on quota */
$upload = [];
$upload['snippet'] = [];
$upload['snippet']['categoryId'] = "22";
$upload['snippet']['description'] = isset($o['desc']) ? $o['desc'] : 'No Description';
$upload['snippet']['title'] = isset($o['title']) ? $o['title'] : 'Title';
$upload['status'] = [];
$upload['status']['privacyStatus'] = isset($o['priv']) ? $o['priv'] : 'private';
$upload['status']['license'] = "youtube";
$content = json_encode($upload);
$content_length = strlen($content);
$video_length = filesize($o['file']);
$ch = curl_init();
curl_setopt_array($ch, [
	CURLOPT_HTTPHEADER => [
		'Authorization: Bearer ' . $oauth['access_token'],
		'Content-Length: ' . $content_length,
		'Content-Type: application/json; charset=UTF-8',
		'X-Upload-Content-Length: ' . $video_length,
		'X-Upload-Content-Type: video/mp4',
	],
	/* https://developers.google.com/youtube/v3/guides/using_resumable_upload_protocol##Start_Resumable_Session */
	CURLOPT_URL => 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=' . urlencode('snippet,status,id,contentDetails') . '&key=' . urlencode($apikey),
	CURLOPT_POST => true,
	CURLOPT_HEADER => true,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_POSTFIELDS => $content,
]);
$data = curl_exec($ch);
curl_close($ch);

/* Resumable should give us a 200 with a Location: to upload to */
if (preg_match('|HTTP/1.1\s(\d+)\s|', $data, $s) !== 1) {
	var_export($data);
	die("\nExpecting 200 from google. didn't see it\n");
}
$status = $s[1] ?? 'unset';
if ($status != '200') {
	var_export($data);
	die("\nExpecting 200 from google. parsed '$status' instead\n");
}
if (preg_match('|Location:\s(.*)|', $data, $m) !== 1) {
	var_export($data);
	die("\nExpecting Location: from google. didn't see it\n");
}
$location = $m[1] ?? 'unset';
if ($location == 'unset') {
	var_export($data);
	die("\nExpecting Location: from google. got nothing from it\n");
}

/* Run a PUT with the file */
$ch = curl_init();
$file_contents = file_get_contents($o['file']);
$content_length = filesize($o['file']);
curl_setopt_array($ch, [
	CURLOPT_HTTPHEADER => [
		'Authorization: Bearer ' . $oauth['access_token'],
		'Content-Length: ' . $content_length,
		'Content-Type: video/mp4',
	],
	CURLOPT_URL => $location,
	CURLOPT_CUSTOMREQUEST => 'PUT',
	CURLOPT_HEADER => true,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_POSTFIELDS => $file_contents,
]);
$data = curl_exec($ch);

/* Lets dump this response so we know what's going on */
echo "File uploaded. Response is the following:\n";
var_export($data);
echo "\n";