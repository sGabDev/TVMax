<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT.'/config/settings.php';
define('DATA_FILE', APP_ROOT.'/data/state.json');
define('UPLOAD_DIR', APP_ROOT.'/uploads/');
require_once __DIR__.'/timeline.php';

date_default_timezone_set('America/Sao_Paulo');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode','1');
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','path'=>'/']);
    if(!is_dir(APP_ROOT.'/data/sessions'))mkdir(APP_ROOT.'/data/sessions',0775,true);
    session_save_path(APP_ROOT.'/data/sessions');
    session_start();
}
require_once __DIR__.'/auth.php';

function default_state(): array {
    return [
        'version' => 1,
        'updatedAt' => time(),
        'screen' => [
            'enabled' => true,
            'background' => '#050816',
            'fit' => 'contain',
            'showClock' => false,
            'showProgress' => true,
            'transition' => 'fade',
            'transitionMs' => 650,
            'volume' => 70,
            'muted' => false
        ],
        'playback' => [
            'mode' => 'auto',
            'currentId' => null,
            'command' => 'play',
            'commandNonce' => 0,
            'startedAt' => time()
        ],
        'queue' => ['autoArchive'=>false],
        'items' => []
    ];
}

function read_state(): array {
    if (!file_exists(DATA_FILE)) {
        write_state(default_state());
    }
    $fp = fopen(DATA_FILE, 'r');
    if (!$fp) return default_state();
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? array_replace_recursive(default_state(), $data) : default_state();
}

function mutate_state(callable $change): array {
    if (!is_dir(dirname(DATA_FILE))) mkdir(dirname(DATA_FILE),0775,true);
    $lock=fopen(DATA_FILE.'.lock','c');
    if(!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Não foi possível bloquear a programação.');
    try {
        $state=read_state();$before=$state;
        if(settle_queue($state,microtime(true)*1000))audit_state_change($before,$state,true);
        $before=$state;$change($state);write_state($state);audit_state_change($before,$state);return read_state();
    }
    finally { flock($lock,LOCK_UN);fclose($lock); }
}

function synchronized_state(): array {
    if(!is_dir(dirname(DATA_FILE)))mkdir(dirname(DATA_FILE),0775,true);
    $lock=fopen(DATA_FILE.'.lock','c');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Não foi possível ler a fila.');
    try{$s=read_state();$before=$s;if(settle_queue($s,microtime(true)*1000)){write_state($s);audit_state_change($before,$s,true);$s=read_state();}return $s;}
    finally{flock($lock,LOCK_UN);fclose($lock);}
}

function public_state(array $state, float $now): array {
    $position=timeline($state,$now);
    anchor_playback($state,$position,$now);
    foreach($state['items'] as &$it) {
        unset($it['serverPath']);
        $it['startsAtMs']=empty($it['startsAt'])?null:strtotime($it['startsAt'])*1000;
        $it['endsAtMs']=empty($it['endsAt'])?null:strtotime($it['endsAt'])*1000;
    }
    return $state;
}

function state_response(?array $state=null): never {
    $state=$state??synchronized_state();
    $now=microtime(true)*1000;
    json_response(['ok'=>true,'state'=>public_state($state,$now),'serverTimeMs'=>$now]);
}

function write_state(array $state): void {
    $state['version'] = (int)($state['version'] ?? 0) + 1;
    $state['updatedAt'] = time();
    foreach ([dirname(DATA_FILE), UPLOAD_DIR] as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0775, true);
    }
    $tmp = tempnam(dirname(DATA_FILE), 'state-');
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if($json===false || file_put_contents($tmp,$json,LOCK_EX)===false || !rename($tmp,DATA_FILE)) {
        if(is_file($tmp))unlink($tmp);
        throw new RuntimeException('Não foi possível salvar a programação. Verifique a permissão da pasta data.');
    }
}

function json_response(array $data, int $status = 200): never {
    if($status>=400&&function_exists('audit_event')){
        $details=['error'=>$data['error']??'Falha'];
        if(in_array($GLOBALS['action']??'', ['login','register','setup'],true))$details['emailAttempt']=mb_substr((string)($_POST['email']??''),0,254);
        audit_event('request.'.($GLOBALS['action']??'unknown'),$details,mb_substr((string)($_POST['id']??''),0,64),'failure');
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_text(string $s, int $max = 5000): string {
    $s = trim(strip_tags($s));
    return mb_substr($s, 0, $max);
}

function item_active(array $item): bool {
    if (empty($item['enabled'])) return false;
    $now = time();
    if (!empty($item['startsAt']) && strtotime($item['startsAt']) > $now) return false;
    if (!empty($item['endsAt']) && strtotime($item['endsAt']) < $now) return false;
    return true;
}

function record_command(array &$state): void {
    $p = &$state['playback'];
    $p['commands'][] = ['nonce'=>$p['commandNonce'], 'command'=>$p['command'],
        'currentId'=>$p['currentId'], 'blackout'=>$p['blackout']??false];
    $p['commands'] = array_slice($p['commands'], -100);
}
