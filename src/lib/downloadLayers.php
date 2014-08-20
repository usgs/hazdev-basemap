<?php

// CONFIGURATION

include_once '../conf/config.inc.php';

// available tile layers
$DOWNLOAD_BASEURL = 'ftp://ftpext.usgs.gov/pub/cr/co/golden/hazdev-basemap';
$DOWNLOADS = array(
	'plates' => 'mbtiles',
	'faults' => 'mbtiles',
	'ushaz' => 'tar.gz'
);


// DOWNLOAD

// check if user wants to download
$answer = null;
while ($answer === null) {
	echo 'Download tile layers? [y/N]: ';
	$answer = strtoupper(trim(fgets(STDIN)));
	if ($answer === '') {
		$answer = 'N';
	} else if ($answer !== 'Y' && $answer !== 'N') {
		$answer = null;
		echo "\n";
	}
}
if ($answer === 'N') {
	return;
}

// download each layer's files
foreach ($DOWNLOADS as $layer => $type) {
	echo PHP_EOL . 'Downloading "' . $layer . '"' . PHP_EOL;
	if ($type === 'mbtiles') {
		$json = $layer . '.jsonp';
		$mbtiles = $layer . '.mbtiles';
		downloadURL($DOWNLOAD_BASEURL . '/' . $json, $TILE_DIR . '/' . $json);
		downloadURL($DOWNLOAD_BASEURL . '/' . $mbtiles, $TILE_DIR . '/' . $mbtiles);
	} else if ($type === 'tar.gz') {
		$tarfile = $layer . '.tar.gz';
		$localTarfile = $TILE_DIR . '/' . $tarfile;
		downloadURL($DOWNLOAD_BASEURL . '/' . $tarfile, $localTarfile);
		echo 'Extracting' . PHP_EOL;
		extractTarGz($localTarfile);
	} else {
		echo 'Unknown type "' . $type . '"';
		exit(1);
	}
}


// UTILITY FUNCTIONS

/**
 * Download a URL into a file.
 *
 * @param $source {String}
 *        url to download.
 * @param $dest {String}
 *        path to destination.
 * @param $showProgress {Boolean}
 *        default true.
 *        output progress to STDERR.
 */
function downloadURL ($source, $dest, $showProgress=true) {
	$curl = curl_init();
	$file = fopen($dest, 'wb');
	curl_setopt_array($curl, array(
			CURLOPT_URL => $source,
			// write output to file
			CURLOPT_FILE => $file,
			// follow redirects
			CURLOPT_FOLLOWLOCATION => 1,
			// show progress
			CURLOPT_NOPROGRESS => ($showProgress ? 0 : 1)));
	curl_exec($curl);
	curl_close($curl);
	fclose($file);
}

/**
 * Extract a gzip compressed tar file.
 *
 * @param $file {String}
 *        path to compressed tar file.
 * @param $removeOriginal {Boolean}
 *        default true.
 *        remove the original file after extraction.
 */
function extractTarGz ($file, $removeOriginal=true) {
	$tar = basename($file, '.gz');
	$dir = basename($tar, '.tar');
	// decompress to tar file
	$gzin = gzopen($file, 'rb');
	$tarout = fopen($tar, 'wb');
	while ($data = gzread($gzin, 1024)) {
		fwrite($tarout, $data);
	}
	fclose($gzin);
	fclose($tarout);
	// extract tar file
	$phar = new PharData($tar);
	$phar->extractTo($dir);
	// cleanup
	unlink($tar);
	if ($removeOriginal) {
		unlink($file);
	}
}

?>
