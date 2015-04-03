<?php

$APP_DIR = dirname(dirname(__FILE__));

$CONFIG = parse_ini_file($APP_DIR . '/conf/config.ini');
$MOUNT_PATH = $CONFIG['MOUNT_PATH'];
$DATA_DIR = $CONFIG['DATA_DIR'];
$TILE_DIR = $DATA_DIR . DIRECTORY_SEPARATOR . 'tiles';

?>
