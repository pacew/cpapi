<?php /* -*- mode:php -*- */

function prettyprint_json ($json) {
	$tname = tempnam ("/tmp", "jq.");
	$json_encoded = json_encode ($json);
	file_put_contents ($tname, $json_encoded);
	$cmd = sprintf ("jq . < %s 2> /dev/null", $tname);
	$ret = shell_exec ($cmd);
	unlink ($tname);
	if (trim ($ret) == "")
		$ret = $json_encoded;
	return ($ret);
}

$cpapi_cookies = array ();

function cpapi_hdr ($handle, $hdr) {
	global $cpapi_cookies;
	if (0)
		printf ("hdr: %s\n", trim ($hdr));

	if (preg_match ("/set-cookie:\\s*([^=]*)=([^;]*)/i", $hdr, $parts)) {
		$name = $parts[1];
		$val = $parts[2];
		$cpapi_cookies[$name] = $val;
	}

	return (strlen ($hdr));
}

function cpapi ($path, $req) {
	global $config, $cpapi_cookies;

	$hdrs = array ();

	$full_url = sprintf ("%s/%s/cpt_webservice/accessapi%s",
			     $config['server'], $config['instance'], $path);
	if (0) {
		printf ("calling: %s\n", $full_url);
	}

	$hdrs[] = sprintf ("x-api-key: %s", $config['accessKey']);
	$hdrs[] = sprintf ("Content-Type: application/json; charset=utf8");

	$cookies = array ();
	foreach ($cpapi_cookies as $name => $val) {
		$cookies[] = sprintf ("%s=%s", $name, $val);
	}

	$curl = curl_init ($full_url);

	curl_setopt ($curl, CURLOPT_HTTPHEADER, $hdrs);
	curl_setopt ($curl, CURLOPT_POST, 1);
	curl_setopt ($curl, CURLOPT_POSTFIELDS, json_encode ($req));
	curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt ($curl, CURLOPT_HEADERFUNCTION, 'cpapi_hdr');

	if (count ($cookies) > 0) {
		curl_setopt ($curl, CURLOPT_COOKIE, implode ('; ', $cookies));
	}

	if (0) {
		curl_setopt ($curl, CURLOPT_VERBOSE, 1);
	}

	$val_str = curl_exec ($curl);

	$val = @json_decode ($val_str, true);

	if ($val == NULL) {
		printf ("can't parse response: %s\n", $val_str);
		exit (1);
	}
	return ($val);
}

function cpapi_connect () {
	global $config;

	$config = @json_decode (file_get_contents ("config.json"), true);
	if ($config == NULL) {
		printf ("can't read config from config.json\n");
		exit (1);
	}

	$req = array ();
	$req['instance'] = $config['instance'];
	$req['username'] = $config['username'];
	$req['password'] = $config['password'];
	$req['remember_me'] = "false";
	$req['timeZoneOffsetMinutes'] = -480;

	/* sets authentication vals in $cpapi_cookies as a side effect */
	$val = cpapi ("/Auth/Authenticate", $req);
	if ($val['resultCode'] != "conWS_Success") {
		printf ("authenticate error:\n");
		var_dump ($val);
		exit (1);
	}
}

function cpapi_exists ($asset_id_or_path) {
	$req = array ();
	$req['assetIdOrPath'] = $asset_id_or_path;
	$val = cpapi ("/Asset/Exists", $req);
	if (@$val['resultCode'] != "conWS_Success") {
		printf ("request error (exists)\n");
		var_dump ($val);
		exit (1);
	}
	return ($val);
}

function cpapi_fields ($asset_id) {
	$req = array ();
	$path = sprintf ("/Asset/Fields/%d", $asset_id);
	$val = cpapi ($path, $req);
	if (@$val['resultCode'] != "conWS_Success") {
		printf ("request error (fields)\n");
		var_dump ($val);
		exit (1);
	}
	return ($val['fields']);
}

function cpapi_read ($asset_id) {
	$req = array ();
	$path = sprintf ("/Asset/Read/%d", $asset_id);
	$val = cpapi ($path, $req);
	if (@$val['resultCode'] != "conWS_Success") {
		printf ("request error (fields)\n");
		var_dump ($val);
		exit (1);
	}
	return ($val['asset']);
}

function cpapi_mkdir ($folder_id, $name) {
	$req = array ();
	$req['newName'] = $name;
	$req['destinationFolderId'] = $folder_id;
	$req['modelId'] = -1;
	$req['type'] = 4;
	$req['devTemplateLanguage'] = -1;
	$req['templateId'] = -1;
	$req['workflowId'] = -1;
	
	$path = "/Asset/Create";
	$val = cpapi ($path, $req);

	if (@$val['resultCode'] != "conWS_Success")
		return (-1);

	$asset = $val['asset'];
	return (intval ($asset['id']));
}

function cpapi_upload ($folder_id, $name, $data) {
	$req = array ();
	$req['newName'] = $name;
	$req['destinationFolderId'] = $folder_id;
	$req['modelId'] = -1;
	$req['workflowId'] = -1;
	$req['bytes'] = base64_encode ($data);

	$path = "/Asset/Upload";
	$val = cpapi ($path, $req);

	if (@$val['resultCode'] != "conWS_Success")
		return (-1);

	$asset = $val['asset'];
	return (intval ($asset['id']));
}

function cpapi_update ($asset_id, $fields) {
	$req = array ();
	$req['assetId'] = $asset_id;
	$req['fields'] = $fields;

	$path = "/Asset/Update";
	$val = cpapi ($path, $req);

	return ($val);

}

