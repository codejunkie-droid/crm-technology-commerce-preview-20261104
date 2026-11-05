<?php
$homePicks=[];
foreach(['laptops','desktops','components','networking','accessories','surveillance','pos'] as $group){
    $items=$store->catalog(['category'=>$group]);
    $homePicks[$group]=array_values(array_filter($items['products'],fn($item)=>!str_starts_with($item['name'],'DEMO')));
}
$hero=$store->product('acer-aspire-lite-intel-celeron-16-screen-512gb-ssd-win-11-home')??$homePicks['laptops'][0]??null;
$featured=array_slice($homePicks['laptops'],0,4);
$promoCopy=[
    ['laptops','A laptop for<br>every ambition.','Work, study & everything between.','Explore laptops'],
    ['desktops','More power.<br>More possibilities.','Find your next desktop setup.','Shop desktop PCs'],
    ['components','Your build.<br>Your rules.','Great things start with the right parts.','Explore components'],
    ['networking','Better connected.<br>Every day.','Bring your home or office together.','Shop networking']
];
?>
<div class="container retail-home">
<section class="upgrade-hero" aria-labelledby="upgrade-title">
  <span class="campaign-device campaign-laptop" aria-hidden="true"><img src="/assets/catalog/7888306077814.webp" width="800" height="800" alt="" fetchpriority="high"></span>
  <span class="campaign-device campaign-desktop" aria-hidden="true"><img src="/assets/catalog/7611169734774.webp" width="586" height="586" alt=""></span>
  <span class="campaign-device campaign-asus" aria-hidden="true"><img src="/assets/catalog/7694225244278.webp" width="586" height="586" alt=""></span>
  <span class="campaign-device campaign-graphics" aria-hidden="true"><img src="/assets/catalog/9052767912054.webp" width="499" height="400" alt=""></span>
  <div class="upgrade-copy"><span class="campaign-label">YOUR NEXT UPGRADE</span><h1 id="upgrade-title">Big ideas.<br><span>Better tech.</span></h1><p>Ready. Set. Upgrade.</p><a class="button hero-cta" href="/catalog">Shop now <?=ui_icon('arrow')?></a></div>
  <span class="hero-corner">LAPTOPS · PCs · COMPONENTS</span>
</section>
<a class="retail-banner" href="/builder"><span class="banner-symbol"><?=ui_icon('chip')?></span><span><strong>Your perfect PC. Built by you.</strong><small>Choose your parts. Check your compatibility.</small></span><b>Start your build <?=ui_icon('arrow')?></b></a>
<section class="promo-grid" aria-label="Explore technology collections"><?php foreach($promoCopy as $i=>[$slug,$heading,$copy,$cta]):$item=$homePicks[$slug][0]??null;?><a class="promo-tile promo-<?=$i+1?>" href="/catalog?category=<?=ui_e($slug)?>"><h2><?=$heading?></h2><p><?=ui_e($copy)?></p><div class="promo-image"><?php if($item):?><img src="<?=ui_e($item['image'])?>" width="360" height="300" loading="lazy" alt=""><?php endif;?></div><span><?=ui_e($cta)?> <?=ui_icon('arrow')?></span></a><?php endforeach;?></section>
<section class="retail-section compact-categories" id="categories"><div class="retail-heading"><h2>What are you looking for?</h2><a href="/catalog">Shop all <?=ui_icon('arrow')?></a></div><div class="retail-category-grid"><?php foreach($categories as $cat):$item=$homePicks[$cat['slug']][0]??null;?><a href="/catalog?category=<?=ui_e($cat['slug'])?>"><span><?php if($item):?><img src="<?=ui_e($item['image'])?>" alt="" width="180" height="150" loading="lazy"><?php else:?><?=ui_icon('box')?><?php endif;?></span><strong><?=ui_e($cat['name'])?></strong></a><?php endforeach;?></div></section>
<section class="retail-section retail-featured"><div class="retail-heading"><div><p>FIND YOUR NEXT FAVOURITE</p><h2>Ready for an upgrade?</h2></div><a href="/catalog">View all <?=ui_icon('arrow')?></a></div><div class="product-tabs" role="group" aria-label="Featured categories"><button class="active" data-featured="laptops">Laptops</button><button data-featured="desktops">Desktop PCs</button><button data-featured="components">Components</button><button data-featured="networking">Networking</button></div><div class="product-grid featured-products"><?php foreach($featured as $p)ui_card($p);?></div><p class="catalog-note">Prices are catalog snapshots. <?=$demo?'Stock is demonstration data; test orders only.':'Please confirm current price and availability.'?></p></section>
<section class="retail-business"><div><span class="campaign-label">CRM TECHNOLOGY FOR BUSINESS</span><h2>Big plans?<br>Let’s get you equipped.</h2><p>From one desk to a whole team. Talk to us about the technology your business needs.</p><a class="button primary" href="/business">Talk to our team <?=ui_icon('arrow')?></a></div><div><a href="/catalog?category=desktops"><?=ui_icon('box')?><span><strong>Equip your workspace</strong><small>Computers, components & essentials</small></span><?=ui_icon('arrow')?></a><a href="/dealer"><?=ui_icon('user')?><span><strong>Buying for your business?</strong><small>Explore dealer account options</small></span><?=ui_icon('arrow')?></a><a href="/support"><?=ui_icon('headphones')?><span><strong>A little advice goes a long way</strong><small>Get help choosing the right technology</small></span><?=ui_icon('arrow')?></a></div></section>
<section class="retail-brands"><h2>Find your favourite brands</h2><div><?php foreach(['Acer','Asus','Lenovo','MSI','Corsair','TP-Link'] as $brand):?><a href="/catalog?brand=<?=rawurlencode($brand)?>"><?=ui_e($brand)?></a><?php endforeach;?></div><p>Explore brands available in our catalog.</p></section>
</div>
