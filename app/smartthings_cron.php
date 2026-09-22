<?php
declare(strict_types=1);
function st_cron_key(bool $create=false): string {
    $db=auth_db();
    $db->exec('CREATE TABLE IF NOT EXISTS smartthings_cron (id INTEGER PRIMARY KEY, secret TEXT NOT NULL)');
    if($create){$q=$db->prepare('INSERT OR IGNORE INTO smartthings_cron(id,secret) VALUES(1,?)');$q->execute([bin2hex(random_bytes(32))]);}
    return (string)$db->query('SELECT secret FROM smartthings_cron WHERE id=1')->fetchColumn();
}
function st_cron_authorized(string $expected,string $provided): bool {
    return strlen($expected)===64&&strlen($provided)===64&&hash_equals($expected,$provided);
}
