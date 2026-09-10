<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/blog.php';
$result=blog_run_schedule(); echo json_encode($result,JSON_UNESCAPED_UNICODE).PHP_EOL;
require dirname(__DIR__).'/app/linkedin.php';
$linkedin=linkedin_run_jobs(); echo json_encode($linkedin,JSON_UNESCAPED_UNICODE).PHP_EOL;
exit(!empty($result['error']) || !empty($linkedin['error'])?1:0);
