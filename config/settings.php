<?php
declare(strict_types=1);
// Environment variables override optional, private local configuration.
$localFile=__DIR__.'/local.php';
$local=is_file($localFile)?require $localFile:[];
$setting=static function(string $name, mixed $default)use($local):mixed{
    $value=getenv('TVMAX_'.$name);
    return $value!==false&&$value!==''?$value:($local[$name]??$default);
};
define('APP_NAME','TVMax');
define('ADMIN_PASSWORD',(string)$setting('ADMIN_PASSWORD','troque-esta-senha'));
define('MAX_UPLOAD_MB',(int)$setting('MAX_UPLOAD_MB',100));
define('CONVERTER_PYTHON',(string)$setting('PYTHON',getenv('STAGETV_PYTHON')?: (PHP_OS_FAMILY==='Windows'?'python':'python3')));
define('CONVERTER_SOFFICE',(string)$setting('SOFFICE',getenv('STAGETV_SOFFICE')?:''));
unset($localFile,$local,$setting);
