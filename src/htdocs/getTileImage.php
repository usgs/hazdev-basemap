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

	http://code.google.com/apis/maps/documentation/javascript/maptypes.html#TileCoordinates
	http://mapbox.com/mbtiles-spec/

*/

date_default_timezone_set('UTC');
include_once('../conf/config.inc.php');


$VALID_EXTENSIONS = array('png', 'jpg');

$x			= intval($_GET['x']);
$y			= intval($_GET['y']);
$zoom		= intval($_GET['zoom']);
$layer	= $_GET['layer'];

$ext		= $_GET['ext']; //should we default to png?

$layer_prefix = $TILE_DIR . DIRECTORY_SEPARATOR .
			str_replace(DIRECTORY_SEPARATOR, '', $layer);
$type = is_dir($layer_prefix) ? 'esri' : 'mbtiles';

$image = null;
$imageFile = null;
$imageSize = null;

if ($type === 'mbtiles') {
	$row = abs($y - (pow(2, $zoom) - 1)); //y is 'flipped' in mbtiles (origin at BL) vs gmaps (origin at TL)
	$col = $x;

	// make sure database exists, then connect

	$db_file = $layer_prefix . '.mbtiles';
	if (!file_exists($db_file)) {
		header('HTTP/1.0 404 File Not Found');
		exit();
	}
	$db = new PDO('sqlite:' . $db_file);

	// get image data from db
	$statement = $db->prepare('SELECT tile_data FROM tiles' .
			' WHERE zoom_level=:zoom AND tile_column=:col AND tile_row=:row');
	$statement->execute(array(
			'zoom' => $zoom,
			'col' => $col,
			'row' => $row));
	$image = $statement->fetch(PDO::FETCH_ASSOC);
	$image = $image['tile_data'];

	// use .jsonp file as a proxy to .mbtiles because php's filemtime fails for
	// .mbtiles (I think b/c it's > 2 GB)
	$jsonp_file = $layer_prefix . '.jsonp';
	$last_mod = filemtime($jsonp_file);

	$db = null;

} else if ($type === 'esri') {
	if (!in_array($ext, $VALID_EXTENSIONS)) {
		header('HTTP/1.0 404 File Not Found');
		exit();
	}

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
	$imageFile = 'images/clear-256x256.png';
}

if ($imageFile) {
	$last_mod = filemtime($imageFile);
	$imageSize = filesize($imageFile);
}

if ($image) {
	$imageSize = strlen($image);
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
	$fp = fopen($imageFile, 'rb');
	fpassthru($fp);
	fclose($fp);
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
