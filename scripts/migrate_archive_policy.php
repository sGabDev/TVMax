<?php
require __DIR__.'/../app/bootstrap.php';
session_write_close();
$state=mutate_state(static function(&$state){});
echo "Archive policy: ".$state['queue']['archivePolicy']."\n";
echo 'Active: '.count(array_filter($state['items'],static fn($it)=>empty($it['archivedAt'])))."\n";
