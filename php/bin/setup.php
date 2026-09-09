<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$file=dirname(__DIR__).'/config/config.local.php';
$config=is_file($file)?require $file:[];
if (!empty($config['admin_password_hash'])) { fwrite(STDERR,"Een beheeraccount bestaat al. Gebruik de wachtwoordfunctie in het beheer.\n"); exit(1); }
$base=$argv[1] ?? 'http://127.0.0.1:8080';
if (!filter_var($base,FILTER_VALIDATE_URL) || !in_array(parse_url($base,PHP_URL_SCHEME),['http','https'],true)) { fwrite(STDERR,"Geef een geldige basis-URL.\n"); exit(1); }
$token=bin2hex(random_bytes(32));
$config=array_replace($config,['base_url'=>rtrim($base,'/'),'admin_email'=>'info@zichtbaar-marketing.nl','app_key'=>$config['app_key'] ?? bin2hex(random_bytes(32)),'setup_token_hash'=>hash('sha256',$token)]);
file_put_contents($file,"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX); chmod($file,0600);
echo "Kies je beheerderswachtwoord via deze eenmalige link:\n".$base.'/admin/setup?token='.$token."\n";
