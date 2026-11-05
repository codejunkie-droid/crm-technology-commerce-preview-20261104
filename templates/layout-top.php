<!doctype html>
<html lang="en-ZA">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=ui_e($title)?> | CRM Technology</title><meta name="description" content="<?=ui_e($description)?>">
<meta name="theme-color" content="#102b4c"><meta name="csrf-token" content="<?=ui_e($session['csrf']??'')?>">
<meta name="robots" content="<?=$demo||!in_array($page,['home','catalog','product'])||!empty($_GET)?'noindex,follow':'index,follow'?>">
<link rel="canonical" href="<?=ui_e(rtrim(getenv('APP_URL')?:'http://localhost:8080','/').$path)?>">
<link rel="stylesheet" href="/assets/app.css"><link rel="stylesheet" href="/assets/retail.css?v=20261105"><script src="/assets/app.js?v=20261105" defer></script>
<?php
$siteBase=rtrim(getenv('APP_URL')?:'http://127.0.0.1:8080','/');
$structured=['@context'=>'https://schema.org','@type'=>'Organization','name'=>'CRM Technology (Pty) Ltd','url'=>$siteBase];
if($page==='product'&&$product){$structured=['@context'=>'https://schema.org','@type'=>'Product','name'=>$product['name'],'description'=>mb_substr($product['description']??'',0,1000),'image'=>$siteBase.$product['image'],'brand'=>['@type'=>'Brand','name'=>$product['brand']],'url'=>$siteBase.'/products/'.$product['slug']];if(!empty($product['sku']))$structured['sku']=$product['sku'];}
?><script type="application/ld+json"><?=json_encode($structured,JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<?php if($page!=='home'):?><script type="application/ld+json"><?=json_encode(['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>$siteBase.'/'],['@type'=>'ListItem','position'=>2,'name'=>$title,'item'=>$siteBase.$path]]],JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><?php endif;?>
</head>
<body data-page="<?=ui_e($page)?>" data-demo="<?=$demo?'true':'false'?>">
<a class="skip-link" href="#main">Skip to content</a>
<div class="retail-utility"><div class="container"><a href="/business">Buying for business? <strong>We can help</strong></a><span>South Africa <b>·</b> ZAR</span><a href="/support">Expert advice &amp; support <?=ui_icon('arrow')?></a></div></div>
<header class="site-header retail-header">
  <div class="container masthead">
    <button class="icon-button mobile-menu-toggle" aria-label="Open navigation" aria-expanded="false" aria-controls="mobile-navigation"><?=ui_icon('menu')?></button>
    <a href="/" class="logo" aria-label="CRM Technology home"><img src="/assets/logo.png" width="120" height="62" alt="CRM Innovating Technologies"><span>TECHNOLOGY<small>YOUR NEXT UPGRADE.</small></span></a>
    <form class="header-search" action="/catalog" role="search"><label class="sr-only" for="search">Search products</label><input id="search" name="q" autocomplete="off" placeholder="Search laptops, PCs &amp; more…" value="<?=ui_e($_GET['q']??'')?>" aria-controls="search-suggestions" aria-expanded="false"><button class="search-submit" type="submit" aria-label="Search products"><span>Search</span><?=ui_icon('search')?></button><div id="search-suggestions" class="search-suggestions" hidden></div></form>
    <nav class="header-actions" aria-label="Account and shopping"><a href="<?=$user?'/account':'/login'?>" class="account-link" aria-label="<?=$user?'Your account':'Sign in'?>"><?=ui_icon('user')?><span><?=$user?'Account':'Sign in'?></span></a><a href="/wishlist" class="header-saved" aria-label="Wishlist"><?=ui_icon('heart')?><span>Saved</span></a><a href="/cart" class="cart-link" aria-label="Shopping basket"><?=ui_icon('cart')?><span class="cart-count"><?=(int)($cart['count']??0)?></span><span class="basket-label">Basket</span></a></nav>
  </div>
  <div class="nav-border"><nav class="container category-nav" aria-label="Product categories"><div class="category-links"><?php foreach($categories as $cat):?><a href="/catalog?category=<?=ui_e($cat['slug'])?>"><?=ui_e($cat['name'])?></a><?php endforeach;?></div><a href="/builder" class="build-nav"><?=ui_icon('chip')?> Build a PC</a><a href="/support" class="nav-help">Help &amp; advice</a></nav></div>
</header>
<dialog id="mobile-navigation" class="mobile-navigation" aria-labelledby="mobile-nav-title"><div class="mobile-nav-head"><h2 id="mobile-nav-title">Shop by category</h2><button class="icon-button" data-close-navigation aria-label="Close navigation"><?=ui_icon('close')?></button></div><nav aria-label="Mobile navigation"><a href="/catalog">Shop all products <?=ui_icon('arrow')?></a><?php foreach($categories as $cat):?><a href="/catalog?category=<?=ui_e($cat['slug'])?>"><?=ui_e($cat['name'])?> <?=ui_icon('arrow')?></a><?php endforeach;?><a href="/builder"><?=ui_icon('chip')?> Build your PC</a><a href="/compare"><?=ui_icon('compare')?> Compare products <span class="compare-count" hidden>0</span></a><a href="/wishlist"><?=ui_icon('heart')?> Saved products</a><a href="/business">Business purchasing <?=ui_icon('arrow')?></a><a href="/support">Help &amp; advice <?=ui_icon('arrow')?></a></nav></dialog>
<div class="retail-benefits"><div class="container"><a href="/support"><?=ui_icon('headphones')?><span>Real people. <strong>Helpful tech advice.</strong></span></a><a href="/compare"><?=ui_icon('compare')?><span>Compare before you choose</span></a><a href="/business"><?=ui_icon('box')?><span>Technology for your business</span></a></div></div>
<?php if($demo):?><div class="preview-bar"><span class="preview-dot"></span><strong>Preview store</strong><span>Test orders only</span><a href="/policies/preview">Details <?=ui_icon('external')?></a></div><?php endif;?>
<main id="main">
