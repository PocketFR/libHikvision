<?php
setlocale(LC_TIME, "fr_FR.utf8",'fra');
date_default_timezone_set('Europe/Paris');
require_once '/var/www/html/records/libHikvision.php';
$cctvs = glob("/mnt/*/*/*/info.bin"); // path to your cameras folder (use * as joker to match all your cameras)
foreach($cctvs as &$cctv) {
        echo "\r\nchangement de camera : $cctv\n";
        $cctv = new hikvisionCCTV($cctv);
        $cctv->eraseSegmentsBefore(strtotime("-30 days midnight"));
}
$logs = glob("/var/log/hikvision/*.log");
foreach ($logs as $log) {
        chown($log, "www-data");
    chgrp($log, "www-data");
        if (filemtime($log)< strtotime("-30 days midnight")) {
                unlink($log);
        }
}
