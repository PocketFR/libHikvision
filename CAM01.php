<?php
error_reporting(E_ALL);
date_default_timezone_set('GMT');
setlocale(LC_TIME, "fr_FR.utf8",'fra');

$site_name="HOME";
$camera_name="CAM01";
$cfgCCTVPaths = "/mnt/records/CAM01/info.bin";

require 'record-viewer.php';
