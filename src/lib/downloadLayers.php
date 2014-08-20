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
if (promptYesNo('Download tile layers?', true)) {
	// download each layer's files
	foreach ($DOWNLOADS as $layer => $type) {
		echo PHP_EOL . 'Downloading "' . $layer . '"' . PHP_EOL;
		if ($type === 'mbtiles') {
			$json = $layer . '.jsonp';
			$mbtiles = $layer . '.mbtiles';
			if (!downloadURL($DOWNLOAD_BASEURL . '/' . $json, $TILE_DIR . '/' . $json)) {
				echo $json . ' already downloaded' . PHP_EOL;
			}
			if (!downloadURL($DOWNLOAD_BASEURL . '/' . $mbtiles, $TILE_DIR . '/' . $mbtiles)) {
				echo $mbtiles . ' already downloaded' . PHP_EOL;
			}
		} else if ($type === 'tar.gz') {
			if (file_exists($TILE_DIR . '/' . $layer)) {
				echo $layer . ' already downloaded' . PHP_EOL;
				continue;
			}
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
}


// UTILITY FUNCTIONS

/**
 * Prompt user with a yes or no question.
 *
 * @param $prompt {String}
 *        yes or no question, should include question mark if desired.
 * @param $default {Boolean}
 *        default null (user must enter y or n).
 *        true for yes to be default answer, false for no.
 *        default answer is used when user presses enter with no other input.
 * @return {Boolean} true if user entered yes, false if user entered no.
 */
function promptYesNo ($prompt='Yes or no?', $default=null) {
	$question = $prompt . ' [' .
			($default === true ? 'Y' : 'y') . '/' .
			($default === false ? 'N' : 'n') . ']: ';

	$answer = null;
	while ($answer === null) {
		echo $question;
		$answer = strtoupper(trim(fgets(STDIN)));
		if ($answer === '') {
			if ($default === true) {
				$answer = 'Y';
			} else if ($default === false) {
				$answer = 'N';
			}
		}
		if ($answer !== 'Y' && $answer !== 'N') {
			$answer = null;
			echo PHP_EOL;
		}
	}
	return ($answer === 'Y');
}

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
 * @return {Boolean} false if $dest already exists, true if created.
 */
function downloadURL ($source, $dest, $showProgress=true) {
	if (file_exists($dest)) {
		return false;
	}
	if ($showProgress) {
		echo 'Downloading "' . $source . '"' . PHP_EOL;
	}
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
	return true;
}

/**
 * Extract a gzip compressed tar file.
 *
 * @param $file {String}
 *        path to compressed tar file.
 * @param $dest {String}
 *        path to extract files into.
 * @param $removeOriginal {Boolean}
 *        default true.
 *        remove the original file after extraction.
 */
function extractTarGz ($file, $dest=null, $removeOriginal=true) {
	$tar = str_replace('.gz', '', $file);
	if ($dest === null) {
		$dest = str_replace('.tar', '', $tar);
	}

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
	$phar->extractTo($dest);
	// cleanup
	unlink($tar);
	if ($removeOriginal) {
		unlink($file);
	}
}

?>
