<?php
declare(strict_types=1);
final class Builder {
    public const GROUPS=['cpu','motherboard','gpu','ram','storage','psu','case','cooling'];
    public static function validate(array $parts): array {
        $warnings=[]; $missing=[]; $total=0; $watts=0;
        foreach($parts as $p)$total+=(int)$p['price_cents'];
        $s=[]; foreach($parts as $group=>$p)$s[$group]=$p['specs'];
        foreach(self::GROUPS as $g)if(!isset($parts[$g]))$missing[]='Choose a '.($g==='psu'?'power supply':$g).'.';
        $compare=function(string $a,string $ak,string $b,string $bk,string $message)use(&$s,&$warnings,&$missing){
            if(!isset($s[$a],$s[$b]))return;
            if(!isset($s[$a][$ak],$s[$b][$bk])){$missing[]=$message.' Required technical data is missing.';return;}
            if(strcasecmp((string)$s[$a][$ak],(string)$s[$b][$bk])!==0)$warnings[]=$message;
        };
        $compare('cpu','socket','motherboard','socket','CPU and motherboard sockets do not match.');
        $compare('ram','memory_type','motherboard','memory_type','Memory generation does not match the motherboard.');
        if(isset($s['cpu'],$s['motherboard'])){
            if(isset($s['cpu']['supported_chipsets'],$s['motherboard']['chipset'])&&!in_array($s['motherboard']['chipset'],$s['cpu']['supported_chipsets'],true))$warnings[]='This chipset is outside the CPU compatibility list.';
            if(!isset($s['motherboard']['bios_verified'])||!$s['motherboard']['bios_verified'])$missing[]='Installed motherboard BIOS and exact CPU support need confirmation.';
        }
        foreach([['ram','modules','motherboard','memory_slots','The memory kit exceeds the available memory slots.'],['ram','capacity_gb','motherboard','max_memory_gb','Memory capacity exceeds the motherboard limit.'],['gpu','length_mm','case','gpu_clearance_mm','The graphics card exceeds case clearance.'],['cooling','height_mm','case','cooler_clearance_mm','The CPU cooler exceeds case clearance.']] as [$a,$ak,$b,$bk,$message])if(isset($s[$a],$s[$b])){
            if(!isset($s[$a][$ak],$s[$b][$bk]))$missing[]=$message.' Clearance/limit data is missing.';
            elseif((int)$s[$a][$ak]>(int)$s[$b][$bk])$warnings[]=$message;
        }
        foreach([['motherboard','form_factor','case','form_factors','The case does not support this motherboard form factor.'],['cpu','socket','cooling','sockets','The cooler does not support this CPU socket.'],['storage','storage_interface','motherboard','storage_interfaces','No matching storage interface is listed.']] as [$a,$ak,$b,$bk,$message])if(isset($s[$a],$s[$b])){
            if(!isset($s[$a][$ak],$s[$b][$bk]))$missing[]=$message.' Required technical data is missing.';
            elseif(!in_array($s[$a][$ak],(array)$s[$b][$bk],true))$warnings[]=$message;
        }
        if(isset($s['gpu'],$s['psu'])){
            if(!isset($s['gpu']['power_connectors'],$s['psu']['power_connectors']))$missing[]='GPU and PSU power connectors need confirmation.';
            elseif(array_diff((array)$s['gpu']['power_connectors'],(array)$s['psu']['power_connectors']))$warnings[]='The power supply is missing a required GPU connector.';
        }
        foreach(['cpu','gpu'] as $g)if(isset($s[$g])){if(isset($s[$g]['tdp']))$watts+=(int)$s[$g]['tdp'];else $missing[]='Power consumption is not supplied for '.$g.'.';}
        if($parts)$watts+=75;
        $recommended=(int)ceil($watts*125/100);
        if(isset($s['psu'])&&!isset($s['psu']['wattage']))$missing[]='Power supply wattage is missing.';
        elseif(isset($s['psu']['wattage'])&&(int)$s['psu']['wattage']<$recommended)$warnings[]='Choose at least '.$recommended.'W for estimated power plus 25% headroom.';
        return ['components'=>$parts,'total_cents'=>$total,'estimated_watts'=>$watts,'recommended_psu_watts'=>$recommended,'status'=>$warnings?'incompatible':($missing?'needs_review':'checks_passed'),'warnings'=>array_values(array_unique($warnings)),'missing'=>array_values(array_unique($missing)),'notice'=>'Checks are limited to supplied specifications. Exact BIOS, connectors, clearance and manufacturer support still require review.'];
    }
}
