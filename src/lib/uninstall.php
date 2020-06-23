<?php

// CONFIGURATION

include_once '../conf/config.inc.php';
if (!defined('NON_INTERACTIVE')) {
	define ('NON_INTERACTIVE', false);
}

// REMOVE PRE-INSTALL FILES

if (promptYesNo('Delete configuration file?', false)) {
	unlink('../conf/config.ini');
}

if (promptYesNo('Delete apache configuration?', true)) {
	unlink('../conf/httpd.conf');
}

if (promptYesNo('Delete data directory?', false)) {
	removeDirectory($DATA_DIR);
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
		if (NON_INTERACTIVE) {
			$answer = '';
		} else {
			$answer = strtoupper(trim(fgets(STDIN)));
		}
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
 * Recursively remove a directory and its contents.
 *
 * @param $dir {String}
 *        directory to remove.
 */
function removeDirectory ($dir) {
	if (!is_dir($dir)) {
		return;
	}

	foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $path) {
		if (is_dir($path)) {
			removeDirectory($path);
		} else {
			unlink($path);
		}
	}
	rmdir($dir);
}

?>