<?php
declare(strict_types=1);
require_once __DIR__.'/linkedin.php';
function handle_linkedin_admin(string $path): never {
    require_admin(); $db=linkedin_db(); $c=config(); $error=null;
    if($path==='/admin/blog/linkedin/callback') {
        $state=$_GET['state'] ?? ''; $saved=$_SESSION['linkedin_oauth'] ?? []; unset($_SESSION['linkedin_oauth']);
        if(!is_string($state) || empty($saved['state']) || !hash_equals($saved['state'],$state) || ($saved['until'] ?? 0)<time()) { http_response_code(401); echo 'Deze verbindingsaanvraag is verlopen of ongeldig. Start opnieuw vanuit het beheer.'; exit; }
        try {
            if(isset($_GET['error'])) throw new RuntimeException('LinkedIn heeft de verbinding niet toegestaan. Controleer de rechten van je app en probeer opnieuw.');
            $code=blog_text($_GET['code'] ?? '',4000);
            $result=linkedin_http('POST','https://www.linkedin.com/oauth/v2/accessToken',http_build_query(['grant_type'=>'authorization_code','code'=>$code,'client_id'=>$c['linkedin_client_id'],'client_secret'=>$c['linkedin_client_secret'],'redirect_uri'=>$saved['redirect']]),false,'application/x-www-form-urlencoded');
            $token=$result['json']['access_token'] ?? ''; $seconds=$result['json']['expires_in'] ?? 0; $scope=$result['json']['scope'] ?? null;
            if($result['status']!==200 || !is_string($token) || strlen($token)<10 || strlen($token)>10000 || preg_match('/\s/',$token) || !is_numeric($seconds) || $seconds<1 || ($scope!==null && !in_array('w_organization_social',explode(' ',urldecode($scope)),true))) throw new RuntimeException('De verbinding is niet voltooid. Controleer de appgegevens en toestemming voor w_organization_social.');
            write_config(['linkedin_token'=>$token,'linkedin_expires'=>time()+(int)$seconds]);
            flash('success','LinkedIn verbonden. Je kunt nu automatisch doorplaatsen inschakelen.');
        } catch(Throwable $exception) { flash('error',$exception instanceof RuntimeException?$exception->getMessage():'De LinkedIn-verbinding is mislukt.'); }
        redirect('/admin/blog/linkedin');
    }
    if($path!=='/admin/blog/linkedin' && $_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Allow: POST'); exit; }
    if($_SERVER['REQUEST_METHOD']==='POST') {
        check_csrf();
        try {
            if($path==='/admin/blog/linkedin/connect') {
                $base=linkedin_site_url();
                if(empty($c['linkedin_client_id']) || empty($c['linkedin_client_secret']) || empty($c['linkedin_author'])) throw new RuntimeException('Sla eerst de appgegevens en het bedrijfspagina-ID op.');
                if(strtolower(parse_url($base,PHP_URL_HOST))!==strtolower(explode(':',$_SERVER['HTTP_HOST'] ?? '')[0])) throw new RuntimeException('Open het beheer op je openbare domein om LinkedIn te verbinden.');
                $callback=$base.'/admin/blog/linkedin/callback'; $state=bin2hex(random_bytes(32));
                $_SESSION['linkedin_oauth']=['state'=>$state,'until'=>time()+600,'redirect'=>$callback];
                redirect('https://www.linkedin.com/oauth/v2/authorization?'.http_build_query(['response_type'=>'code','client_id'=>$c['linkedin_client_id'],'redirect_uri'=>$callback,'state'=>$state,'scope'=>'w_organization_social']));
            } elseif($path==='/admin/blog/linkedin/disconnect') {
                write_config(['linkedin_token'=>'','linkedin_expires'=>0,'linkedin_enabled'=>false]); unset($_SESSION['linkedin_oauth']); flash('success','Verbinding verwijderd en automatisch doorplaatsen uitgezet.'); redirect('/admin/blog/linkedin');
            } elseif($path==='/admin/blog/linkedin/retry') {
                $id=blog_text($_POST['post_id'] ?? '',32); $query=$db->prepare('SELECT * FROM linkedin_jobs WHERE post_id=?'); $query->execute([$id]); $job=$query->fetch();
                if(!$job || !in_array($job['status'],['failed','uncertain'],true)) throw new RuntimeException('Dit bericht kan niet opnieuw worden ingepland.');
                if($job['status']==='uncertain' && ($_POST['checked_linkedin'] ?? '')!=='yes') throw new RuntimeException('Controleer eerst LinkedIn en bevestig dat het bericht daar niet staat.');
                $db->prepare("UPDATE linkedin_jobs SET status='pending',image_urn='',message='Opnieuw ingepland door beheerder.',updated_at=? WHERE post_id=? AND status IN ('failed','uncertain')")->execute([time(),$id]);
                flash('success','Het bericht wordt bij de volgende geplande taak opnieuw verwerkt.'); redirect('/admin/blog/linkedin');
            } elseif($path==='/admin/blog/linkedin/skip') {
                $id=blog_text($_POST['post_id'] ?? '',32);
                $db->prepare("UPDATE linkedin_jobs SET status='cancelled',message='Door beheerder afgehandeld; niet opnieuw verzenden.' WHERE post_id=? AND status IN ('failed','uncertain','pending')")->execute([$id]); flash('success','Bericht afgehandeld. Het wordt niet opnieuw verzonden.'); redirect('/admin/blog/linkedin');
            } elseif($path==='/admin/blog/linkedin') {
                $client=blog_text($_POST['client_id'] ?? '',200); $secret=blog_text($_POST['client_secret'] ?? '',1000,false); $organization=blog_text($_POST['organization_id'] ?? '',30); $version=blog_text($_POST['version'] ?? '202606',6);
                if(!preg_match('/^[a-zA-Z0-9_-]+$/',$client) || !preg_match('/^[1-9][0-9]*$/',$organization) || !preg_match('/^20\d{2}(?:0[1-9]|1[0-2])$/',$version)) throw new RuntimeException('Controleer Client ID, numeriek bedrijfspagina-ID en API-versie (YYYYMM).');
                $enabled=($_POST['enabled'] ?? '')==='yes'; $changed=$client!==$c['linkedin_client_id'] || 'urn:li:organization:'.$organization!==$c['linkedin_author'] || $secret!=='';
                if($enabled) { linkedin_site_url(); if($changed || empty($c['linkedin_token']) || (int)$c['linkedin_expires']<=time()+60) throw new RuntimeException('Sla de gegevens eerst op zonder automatisch doorplaatsen en verbind vervolgens met LinkedIn.'); }
                $values=['linkedin_client_id'=>$client,'linkedin_author'=>'urn:li:organization:'.$organization,'linkedin_version'=>$version,'linkedin_enabled'=>$enabled,'linkedin_enabled_since'=>$enabled && empty($c['linkedin_enabled'])?time()+1:$c['linkedin_enabled_since']];
                if($secret!=='') $values['linkedin_client_secret']=$secret;
                if($changed) { $values['linkedin_token']=''; $values['linkedin_expires']=0; $values['linkedin_enabled']=false; }
                write_config($values); flash('success','LinkedIn-instellingen opgeslagen. Alleen nieuwe publicaties worden automatisch toegevoegd; bestaande blogartikelen worden niet in één keer gedeeld.'); redirect('/admin/blog/linkedin');
            } else { http_response_code(404); throw new RuntimeException('Onbekende actie.'); }
        } catch(RuntimeException|InvalidArgumentException $exception) { $error=$exception->getMessage(); }
    }
    $title='LinkedIn doorplaatsen — Zichtbaar Marketing'; $description=''; require dirname(__DIR__).'/templates/head.php';
    $notice=$_SESSION['flash'] ?? null; unset($_SESSION['flash']);
    require dirname(__DIR__).'/templates/linkedin-settings.php'; exit;
}
