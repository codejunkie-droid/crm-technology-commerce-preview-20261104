<?php
declare(strict_types=1);
function seedCatalog(Database $db): void {
    foreach ([['laptops','Laptops','Portable power for work and play.'],['desktops','Desktop PCs','Purpose-built computing.'],['components','Components','The foundation of your next build.'],['accessories','Accessories','Every detail of your setup.'],['surveillance','Surveillance','A clearer view of your security.'],['networking','Networking','Stay intelligently connected.'],['pos','POS Systems','Technology for your business.']] as $i=>$c) {
        if(!$db->one('SELECT id FROM categories WHERE slug=?',[$c[0]]))$db->insert('categories',['slug'=>$c[0],'name'=>$c[1],'description'=>$c[2],'position'=>$i]);
    }
    if((int)$db->run('SELECT COUNT(*) FROM products')->fetchColumn()>0)return;
    $path=CRM_ROOT.'/database/catalog-snapshot.json';
    $catalog=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):[];
    $fixtures=[
        ['cpu','Demo AM5 Processor',350000,['component_type'=>'cpu','socket'=>'AM5','tdp'=>65,'supported_chipsets'=>['B650','X670']],'/assets/product-placeholder.svg'],
        ['motherboard','Demo B650 ATX Motherboard',280000,['component_type'=>'motherboard','socket'=>'AM5','chipset'=>'B650','memory_type'=>'DDR5','memory_slots'=>4,'max_memory_gb'=>128,'form_factor'=>'ATX','storage_interfaces'=>['M.2','SATA'],'bios_requirement'=>'Confirm CPU support list and installed BIOS.'], '/assets/product-placeholder.svg'],
        ['gpu','Demo Graphics Card',550000,['component_type'=>'gpu','tdp'=>200,'length_mm'=>280,'power_connectors'=>['8-pin']],'/assets/product-placeholder.svg'],
        ['ram','Demo 32GB DDR5 Memory Kit',160000,['component_type'=>'ram','memory_type'=>'DDR5','capacity_gb'=>32,'modules'=>2],'/assets/product-placeholder.svg'],
        ['storage','Demo 1TB M.2 SSD',100000,['component_type'=>'storage','storage_interface'=>'M.2'], '/assets/product-placeholder.svg'],
        ['psu','Demo 750W Power Supply',130000,['component_type'=>'psu','wattage'=>750,'power_connectors'=>['8-pin','24-pin','CPU 8-pin']],'/assets/product-placeholder.svg'],
        ['case','Demo ATX Tower Case',120000,['component_type'=>'case','form_factors'=>['ATX','Micro-ATX','Mini-ITX'],'gpu_clearance_mm'=>350,'cooler_clearance_mm'=>165],'/assets/product-placeholder.svg'],
        ['cooling','Demo Tower CPU Cooler',65000,['component_type'=>'cooling','sockets'=>['AM5','AM4'],'height_mm'=>155,'tdp_capacity'=>150],'/assets/product-placeholder.svg'],
        ['motherboard','Demo AM4 DDR4 Motherboard',180000,['component_type'=>'motherboard','socket'=>'AM4','chipset'=>'B550','memory_type'=>'DDR4','memory_slots'=>2,'max_memory_gb'=>64,'form_factor'=>'Micro-ATX','storage_interfaces'=>['M.2','SATA']],'/assets/product-placeholder.svg']
    ];
    foreach($fixtures as $i=>$f)$catalog[]=['name'=>$f[1],'slug'=>'demo-'.strtolower(str_replace(' ','-',$f[1])).'-'.$i,'brand'=>'Demo fixtures','category_slug'=>'components','price_cents'=>$f[2],'sku'=>'DEMO-'.strtoupper($f[0]).'-'.$i,'image'=>$f[4],'specs'=>$f[3],'description'=>'Synthetic test component for demonstrating compatibility rules. Not a real CRM Technology product, specification or offer.','source_url'=>'','source_id'=>'fixture-'.$i,'is_demo'=>1,'fixture'=>true];
    foreach($catalog as $i=>$p){
        $cat=$db->one('SELECT id FROM categories WHERE slug=?',[$p['category_slug']??'accessories'])??$db->one('SELECT id FROM categories WHERE slug=?',['accessories']);
        $brandName=trim($p['brand']??'')?:'Unspecified';
        $brand=$db->one('SELECT id FROM brands WHERE name=?',[$brandName]);
        $brandId=$brand?(int)$brand['id']:$db->insert('brands',['name'=>$brandName]);
        $slug=$p['slug']??'import-'.$i;
        if($db->one('SELECT id FROM products WHERE slug=?',[$slug]))continue;
        $id=$db->insert('products',['category_id'=>$cat['id'],'brand_id'=>$brandId,'slug'=>$slug,'name'=>$p['name'],'description'=>$p['description']??'','image'=>$p['image']??'/assets/product-placeholder.svg','specs'=>json_encode($p['specs']??[]),'is_demo'=>1,'source_url'=>$p['source_url']??null,'active'=>1,'featured'=>$i<8?1:0,'created_at'=>gmdate('c')]);
        $variants=$p['variants']??[];
        if(!$variants)$variants=[['sku'=>$p['sku']??'','price_cents'=>$p['price_cents']??0,'old_price_cents'=>$p['old_price_cents']??null,'label'=>'Standard']];
        foreach($variants as $j=>$v) {
            $sourceSku=(string)($v['sku']??$p['sku']??'');
            $internalSku=$sourceSku!==''?$sourceSku:'IMPORT-'.($p['source_id']??$id).'-'.$j;
            if($db->one('SELECT id FROM product_variants WHERE sku=?',[$internalSku]))$internalSku.='-'.$id.'-'.$j;
            $attrs=$v['attributes']??[]; $attrs['source_sku']=$sourceSku; $attrs['fixture']=(bool)($p['fixture']??false);
            $db->insert('product_variants',['product_id'=>$id,'sku'=>$internalSku,'fincon_sku'=>null,'label'=>$v['label']??$v['title']??$v['name']??'Standard','price_cents'=>(int)($v['price_cents']??$p['price_cents']??0),'dealer_price_cents'=>null,'old_price_cents'=>$v['old_price_cents']??$p['old_price_cents']??null,'stock'=>($p['source_available']??true)?10:0,'attributes'=>json_encode($attrs),'stock_verified_at'=>null]);
        }
    }
    foreach(['announcement'=>'Technology for your next chapter.','company_name'=>'CRM Technology (Pty) Ltd','vat_basis'=>'unverified','cart_recovery_enabled'=>'0','marketing_enabled'=>'0','shipping_enabled'=>'0','payments_enabled'=>'0'] as $k=>$v)if(!$db->one('SELECT name FROM settings WHERE name=?',[$k]))$db->run('INSERT INTO settings(name,value) VALUES(?,?)',[$k,$v]);
}
