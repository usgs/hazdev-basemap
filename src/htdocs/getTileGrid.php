<?php

/*

	v1, 2012-04-26, SAH
		Retrieves json utfgrid tiles for tile layers in mbtiles sqlite format
	v2, 2014-08-18, HAS
		Updated to use PDO and eliminate template dependency

	Inputs:

	x: tile coordintate - column
	y: tile coordintate - row
	zoom: current zoom level
	layer: database / dir name where map tiles are stored

	More info:

	https://mapbox.com/mbtiles-spec/
	https://mapbox.com/mbtiles-spec/utfgrid/

*/


date_default_timezone_set('UTC');
include_once('../conf/config.inc.php');

$x			= intval($_GET['x']);
$y			= intval($_GET['y']);
$zoom		= intval($_GET['zoom']);
$layer	= $_GET['layer'];
$callback = isset($_GET['callback']) ? $_GET['callback'] : 'grid';

if ($callback === '') {
	$callback = 'grid';
}

// restrict allowed callback names
if (!preg_match('/^[A-Za-z0-9\._]+$/', $callback)) {
	header('HTTP/1.0 400 Bad Request');
	echo 'Bad callback value, valid characters include [A-Za-z0-9\._]';
	exit();
}

// range check for x and y
$n = pow(2, $zoom) - 1;
if ($x > $n || $y > $n) {
	header('HTTP/1.0 404 File Not Found');
	exit();
}

// y is 'flipped' in mbtiles (origin at BL) vs gmaps (origin at TL)
$row = abs($y - $n);
$col = $x;

// make sure database exists, then connect
$layer_prefix = $TILE_DIR . DIRECTORY_SEPARATOR .
		str_replace(DIRECTORY_SEPARATOR, '', $layer);
$db_file = $layer_prefix . '.mbtiles';
if (!file_exists($db_file)) {
	header('HTTP/1.0 404 File Not Found');
	exit();
}
$db = new PDO('sqlite:' . $db_file);
// use .jsonp file as a proxy to .mbtiles because php's filemtime fails for
// .mbtiles (I think b/c it's > 2 GB)
$mod = filemtime($layer_prefix . '.jsonp');
$expires = date('D, d M Y h:i:s T', strtotime('+1 month'));


// Set content type headers regardless of whether there is data or not.
header('Content-Type: text/javascript');
header('Cache-Control: public');
header("Last-Modified: $mod");
header("Expires: $expires");

// get utfgrid data from db
$statement = $db->prepare('SELECT grid FROM grids' .
		' WHERE zoom_level=? AND tile_column=? AND tile_row=?');
$statement->bindParam(1, $zoom, PDO::PARAM_INT);
$statement->bindParam(2, $col, PDO::PARAM_INT);
$statement->bindParam(3, $row, PDO::PARAM_INT);
$statement->execute();
$grid = $statement->fetch(PDO::FETCH_ASSOC);
$statement = null;
if (!isset($grid['grid'])) {
	// no data
	$json = 'null';
} else {
	$utfgrid = gzinflate(substr($grid['grid'], 2));

	// get feature data from db
	$features = array();
	$statement = $db->prepare('SELECT key_name, key_json FROM grid_data' .
			' WHERE zoom_level=? and tile_column=? and tile_row=?');
	$statement->bindParam(1, $zoom, PDO::PARAM_INT);
	$statement->bindParam(2, $col, PDO::PARAM_INT);
	$statement->bindParam(3, $row, PDO::PARAM_INT);
	$statement->execute();
	while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$features[] = '"' . $row['key_name'] . '":' . $row['key_json'];
	}
	$statement = null;

	// Combine 'utfgrid' and 'features' array values
	// utfgrid is a json object as a string, substr removes closing '}'
	$json = substr($utfgrid, 0, -1) .
			', "data":{' . implode(',', $features) . '}' .
			'}';
}
$db = null;

$jsonp = $callback . '(' . $json . ');';
header('Content-Length: ' . strlen($jsonp));
echo $jsonp;

?>
