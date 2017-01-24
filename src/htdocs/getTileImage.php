<?php

/*

	v1.1, 2012-04-26, SAH
	v2, 2014-08-18, HAS
		Updated to use PDO and eliminate template dependency

	Retrieves cached map tiles for tile layers in either mbtiles sqlite or ESRI ArcGIS Server 'exploded' format

	Inputs:

	x: tile coordintate - column
	y: tile coordintate - row
	zoom: current zoom level
	layer: database / dir name where map tiles are stored
	ext: image format (.png default)
	type: mbtiles or esri

	More info:

	https://code.google.com/apis/maps/documentation/javascript/maptypes.html#TileCoordinates
	https://mapbox.com/mbtiles-spec/

*/

date_default_timezone_set('UTC');
include_once('../conf/config.inc.php');


$VALID_EXTENSIONS = array('png', 'jpg');

$x			= intval($_GET['x']);
$y			= intval($_GET['y']);
$zoom		= intval($_GET['zoom']);
$layer	= $_GET['layer'];

$ext		= $_GET['ext']; //should we default to png?

// range check for x and y
$n = pow(2, $zoom) - 1;
if ($x > $n || $y > $n) {
	header('HTTP/1.0 404 File Not Found');
	exit();
}

if (!in_array($ext, $VALID_EXTENSIONS)) {
	header('HTTP/1.0 404 File Not Found');
	exit();
}


$layer_prefix = $TILE_DIR . DIRECTORY_SEPARATOR .
			str_replace(DIRECTORY_SEPARATOR, '', $layer);
$type = is_dir($layer_prefix) ? 'esri' : 'mbtiles';

$image = null;
$imageFile = null;
$imageSize = null;

if ($type === 'mbtiles') {
	//y is 'flipped' in mbtiles (origin at BL) vs gmaps (origin at TL)
	$row = abs($y - $n);
	$col = $x;

	// make sure database exists, then connect
	$db_file = $layer_prefix . '.mbtiles';
	if (!file_exists($db_file)) {
		header('HTTP/1.0 404 File Not Found');
		exit();
	}

	// get image data from db
	$db = new PDO('sqlite:' . $db_file);
	$statement = $db->prepare('SELECT tile_data FROM tiles' .
			' WHERE zoom_level=? AND tile_column=? AND tile_row=?');
	$statement->bindParam(1, $zoom, PDO::PARAM_INT);
	$statement->bindParam(2, $col, PDO::PARAM_INT);
	$statement->bindParam(3, $row, PDO::PARAM_INT);
	$statement->execute();
	$image = $statement->fetch(PDO::FETCH_ASSOC);
	if (isset($image['tile_data'])) {
		$image = $image['tile_data'];
		$imageSize = strlen($image);
		// use .jsonp file as a proxy to .mbtiles because php's filemtime fails for
		// .mbtiles (I think b/c it's > 2 GB)
		$last_mod = filemtime($layer_prefix . '.jsonp');
	} else {
		$image = null;
	}
	$statement = null;
	$db = null;
} else if ($type === 'esri') {
	$row = 'R' . num2hex($y);
	$col = 'C' . num2hex($x);
	$level = 'L' . str_pad($zoom, 2, '0', STR_PAD_LEFT);
	$file = $layer_prefix . DIRECTORY_SEPARATOR .
			$level . DIRECTORY_SEPARATOR .
			$row . DIRECTORY_SEPARATOR .
			$col . '.' . $ext;
	if (file_exists($file)) {
		$imageFile = $file;
	}
}

if (!$image && !$imageFile) {
	$imageFile = $APP_DIR . '/htdocs/images/clear-256x256.png';
}

if ($imageFile) {
	$last_mod = filemtime($imageFile);
	$imageSize = filesize($imageFile);
}


$last_mod	= date('D, d M Y h:i:s T', $last_mod);
$expires = date('D, d M Y h:i:s T', strtotime('+1 month'));
//$expires = date('D, d M Y h:i:s T', strtotime('now'));

header('Content-Transfer-Encoding: binary');
header("Content-Type: image/$ext");
header('Cache-Control: public');
header("Content-Length: $imageSize");
header("Expires: $expires");
header("Last-Modified: $last_mod");


if ($imageFile) {
	readfile($imageFile);
} else if ($image) {
	echo $image;
}

// convert x and y to ESRI (hex) format
function num2hex($num) {
	$hex = base_convert($num, 10, 16);
	$r = str_pad($hex, 8, '0', STR_PAD_LEFT);
	return $r;
}

?>
