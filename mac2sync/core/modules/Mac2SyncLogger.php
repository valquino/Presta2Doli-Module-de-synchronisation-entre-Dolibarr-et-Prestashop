<?php
function mac2sync_log($content){
    $filename = DOL_DOCUMENT_ROOT . "/custom/mac2sync/core/modules/mac2sync_log.txt";
    $content = "[". date('Y-m-d H:i:s')."]" . $content . " \n";
    if (!$handle = fopen($filename, 'a')) {
        echo "Cannot open file ($filename)";
        exit;
   }
    if (fwrite($handle, $content) === FALSE) {
        dol_syslog("Cannot write to file ");
    }
    fclose($handle);
}

function mac2sync_debug($content){
    global $conf;
    if($conf->global->MAC2SYNC_DEBUG == 1){
        var_dump($content);
    }
}
?>