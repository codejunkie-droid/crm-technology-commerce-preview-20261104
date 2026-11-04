<!doctype html>
<html lang="en-ZA">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=ui_e($title)?> | CRM Technology</title><meta name="description" content="<?=ui_e($description)?>">
<meta name="theme-color" content="#0B1020"><meta name="csrf-token" content="<?=ui_e($session['csrf']??'')?>">
<meta name="robots" content="<?=$demo||!in_array($page,['home','catalog','product'])||!empty($_GET)?'noindex,follow':'index,follow'?>">
<link rel="canonical" href="<?=ui_e(rtrim(getenv('APP_URL')?:'http://localhost:8080','/').$path)?>">
<link rel="stylesheet" href="/assets/app.css"><script src="/assets/app.js" defer></script>
<?php
$siteBase=rtrim(getenv('APP_URL')?:'http://127.0.0.1:8080','/');
$structured=['@context'=>'https://schema.org','@type'=>'Organization','name'=>'CRM Technology (Pty) Ltd','url'=>$siteBase];
if($page==='product'&&$product){$structured=['@context'=>'https://schema.org','@type'=>'Product','name'=>$product['name'],'description'=>mb_substr($product['description']??'',0,1000),'image'=>$siteBase.$product['image'],'brand'=>['@type'=>'Brand','name'=>$product['brand']],'url'=>$siteBase.'/products/'.$product['slug']];if(!empty($product['sku']))$structured['sku']=$product['sku'];}
?><script type="application/ld+json"><?=json_encode($structured,JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<?php if($page!=='home'):?><script type="application/ld+json"><?=json_encode(['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$siteBase.'/'],['@type'=>'ListItem','position'=>2,'name'=>$title,'item'=>$siteBase.$path]]],JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><?php endif;?>
</head>
<body data-page="<?=ui_e($page)?>" data-demo="<?=$demo?'true':'false'?>">
<a class="skip-link" href="#main">Skip to content</a>
<div class="announcement"><div class="container announcement-inner"><span><?=ui_e($store->settings()['announcement']??'Smart technology. Real possibilities.')?></span><a href="/business">Buying for your business? Let's talk <?=ui_icon('arrow')?></a><span class="region">South Africa <b> / </b> ZAR</span></div></div>
<header class="site-header">
<div class="container masthead"><button class="icon-button mobile-menu-toggle" aria-label="Open navigation" aria-expanded="false"><?=ui_icon('menu')?></button><a href="/" class="logo" aria-label="CRM Technology home"><img src="/assets/logo.png" width="100" height="52" alt="CRM Innovating Technologies"><span>TECHNOLOGY<span>Built for what’s next.</span></span></a>
<form class="header-search" action="/catalog" role="search"><label class="sr-only" for="search">Search products</label><?=ui_icon('search')?><input id="search" name="q" autocomplete="off" placeholder="Find your next upgrade…" value="<?=ui_e($_GET['q']??'')?>" aria-controls="search-suggestions" aria-expanded="false"><kbd>/</kbd><div id="search-suggestions" class="search-suggestions" hidden></div></form>
<nav class="header-actions" aria-label="Account and shopping"><a href="<?=$user?'/account':'/login'?>" class="account-link" aria-label="<?=$user?'Your account':'Sign in'?>"><?=ui_icon('user')?><span><?=$user?'My account':'Sign in'?><small><?=$user?ui_e(explode(' ',$user['name'])[0]):'Your account'?></small></span></a><a href="/wishlist" class="icon-button" aria-label="Wishlist"><?=ui_icon('heart')?></a><a href="/compare" class="icon-button compare-header" aria-label="Compare products"><?=ui_icon('compare')?><span class="compare-count" hidden>0</span></a><a href="/cart" class="cart-link" aria-label="Shopping basket"><?=ui_icon('cart')?><span class="cart-count"><?=(int)($cart['count']??0)?></span></a></nav></div>
<div class="nav-border"><nav class="container category-nav" aria-label="Product categories"><div class="category-links"><?php foreach($categories as $cat):?><div class="nav-category"><a href="/catalog?category=<?=ui_e($cat['slug'])?>"><?=ui_e($cat['name'])?></a><div class="mega-menu"><div><p class="eyebrow">EXPLORE THE COLLECTION</p><h2><?=ui_e($cat['name'])?></h2><p><?=ui_e($cat['description']??'Find the right technology for your next project.')?></p><a class="text-link" href="/catalog?category=<?=ui_e($cat['slug'])?>">Shop all <?=ui_e(strtolower($cat['name']))?> <?=ui_icon('arrow')?></a></div><div><p class="eyebrow">SHOP YOUR WAY</p><a href="/catalog?category=<?=ui_e($cat['slug'])?>&sort=newest">Recently added</a><a href="/catalog?category=<?=ui_e($cat['slug'])?>&sort=price-asc">Price: low to high</a><a href="/compare">Compare specifications</a><a href="/business">Business purchasing</a></div></div></div><?php endforeach;?></div><a href="/builder" class="build-nav"><?=ui_icon('chip')?> Build your PC <?=ui_icon('arrow')?></a></nav></div>
</header>
<?php if($demo):?><div class="preview-bar"><span class="preview-dot"></span> PREVIEW STORE <span>Catalog snapshot · Test orders only</span><a href="/policies/preview">About this preview <?=ui_icon('external')?></a></div><?php endif;?>
<main id="main">
