<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self'; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self' https://www.payfast.co.za https://sandbox.payfast.co.za");
header('Cache-Control: private, no-store');
$httpsPublicUrl = str_starts_with((string)(getenv('APP_URL') ?: ''), 'https://');
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || $httpsPublicUrl) header('Strict-Transport-Security: max-age=31536000');
ob_start();
set_exception_handler(function(Throwable $error): void {
    error_log('CRM request failed: '.$error->getMessage());
    while(ob_get_level())ob_end_clean();
    $code=$error instanceof HttpError?$error->status:500;
    http_response_code($code);
    $message=$error instanceof HttpError?$error->getMessage():'This page is temporarily unavailable. Please try again shortly.';
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CRM Technology</title><link rel="stylesheet" href="/assets/app.css"><main class="container page-shell"><div class="empty-state"><p class="eyebrow">CRM TECHNOLOGY</p><h1>Let’s get you back on track.</h1><p>'.htmlspecialchars($message,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p><a class="button primary" href="/">Return to the store</a></div></main></html>';
});
require __DIR__.'/../src/bootstrap.php';
require_once __DIR__.'/../src/Api.php';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if(preg_match('~^/build/([a-f0-9]+)$~',$path,$shared)){header('Location: /builder?build='.rawurlencode($shared[1]),true,302);exit;}
if($path==='/robots.txt'){
    header('Content-Type: text/plain; charset=UTF-8');
    echo $store->demo?"User-agent: *\nDisallow: /\n":"User-agent: *\nDisallow: /api/\nDisallow: /admin\nDisallow: /account\nDisallow: /checkout\nDisallow: /cart\nDisallow: /login\nDisallow: /register\nDisallow: /*?\nSitemap: ".rtrim(getenv('APP_URL')?:'http://localhost:8080','/')."/sitemap.xml\n";exit;
}
if($path==='/sitemap.xml'){
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    if(!$store->demo){
        $base=rtrim(getenv('APP_URL')?:'http://localhost:8080','/');$urls=['/','/catalog','/business'];
        foreach($store->categories() as $c)$urls[]='/category/'.$c['slug'];
        $catalog=$store->catalog(['limit'=>100]);
        for($i=1;$i<=($catalog['pages']??1);$i++){if($i>1)$catalog=$store->catalog(['limit'=>100,'page'=>$i]);foreach($catalog['products'] as $item)if(empty($item['is_demo']))$urls[]='/products/'.$item['slug'];}
        foreach($urls as $url)echo '<url><loc>'.htmlspecialchars($base.$url,ENT_XML1,'UTF-8').'</loc></url>';
    }echo '</urlset>';exit;
}
if (str_starts_with($path, '/api/')) { handleApi($store); exit; }
require __DIR__.'/../templates/helpers.php';
$session = $store->session(); $user = $session['user'] ?? null; $demo = $session['demo'] ?? true;
$cart = $store->cart(); $categories = $store->categories();
$all = $store->catalog(['limit'=>100]);
$products = $all['products'] ?? []; $page = trim($path,'/') ?: 'home';
$title = 'Technology. Without compromise.'; $description = 'Explore computing, components, connectivity and business technology at CRM Technology.';
$status = 200; $product = null; $listing = null;
if (preg_match('~^products/(.+)$~',$page,$m)) { $product=$store->product($m[1]); if(!$product){$page='not-found';$status=404;}else{$page='product';$title=$product['name'];$description=mb_substr(strip_tags($product['description']??''),0,155);} }
elseif(preg_match('~^category/(.+)$~',$page,$m)){$_GET['category']=$m[1];$page='catalog';}
if ($page==='search') $page='catalog';
if ($page==='catalog') { $listing=$store->catalog($_GET); $title=!empty($_GET['q'])?'Search: '.$_GET['q']:(!empty($_GET['category'])?ui_category($_GET['category']):'Explore the collection'); }
$titles=['cart'=>'Your basket','checkout'=>'Checkout','login'=>'Welcome back','register'=>'Create your account','account'=>'Your account','dealer'=>'Business, with an advantage.','wishlist'=>'Your saved collection','compare'=>'The details make the difference.','builder'=>'Build beyond limits.','support'=>'How can we help?','admin'=>'Commerce workspace','business'=>'Technology that means business.','forgot'=>'Reset your password','reset'=>'Choose a new password'];
$title=$titles[$page]??$title;
if (str_starts_with($page,'policies/')) $title=ui_category(substr($page,9));
$allowed=['home','catalog','product','cart','checkout','login','register','account','dealer','wishlist','compare','builder','support','admin','business','forgot','reset','not-found'];
if(!in_array($page,$allowed,true)&&!str_starts_with($page,'policies/')){$page='not-found';$status=404;}
http_response_code($status);
require __DIR__.'/../templates/layout-top.php';
if ($page==='home') require __DIR__.'/../templates/home.php';
elseif ($page==='catalog'||$page==='product') require __DIR__.'/../templates/catalog.php';
else require __DIR__.'/../templates/pages.php';
require __DIR__.'/../templates/layout-bottom.php';
