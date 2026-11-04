<?php
declare(strict_types=1);
/** Dependency-free, paginated A4 quotation renderer for shared hosting. */
final class Pdf {
    private static function money(int $c):string { return 'R '.number_format(intdiv($c,100),0,'.',' ').'.'.str_pad((string)($c%100),2,'0',STR_PAD_LEFT); }
    private static function esc(string $s):string { $s=iconv('UTF-8','Windows-1252//TRANSLIT',$s)?:$s;return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$s); }
    private static function text(string $s,int $x,int $y,int $size=10,bool $bold=false,string $color='0.08 0.10 0.17'):string { return "$color rg BT /".($bold?'F2':'F1')." $size Tf 1 0 0 1 $x $y Tm (".self::esc($s).") Tj ET\n"; }
    private static function rule(int $y,string $color='0.87 0.89 0.93'):string { return "$color RG 0.7 w 42 $y m 553 $y l S\n"; }
    public static function quote(array $quote,array $items): string {
        $customer=json_decode($quote['customer_snapshot'],true)?:[];
        $chunks=[];$chunk=[];$height=0;
        foreach($items as $item){$lines=explode("\n",wordwrap($item['name'],50,"\n",true));$rowHeight=max(49,count($lines)*13+22);if($height+$rowHeight>385&&$chunk){$chunks[]=$chunk;$chunk=[];$height=0;}$chunk[]=[$item,$lines,$rowHeight];$height+=$rowHeight;}
        if($chunk)$chunks[]=$chunk;if(!$chunks)$chunks=[[]];
        $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];$kids=[];$next=5;
        foreach($chunks as $pageNo=>$rows){
            $id=$next++;$contentId=$next++;$kids[]="$id 0 R";
            $s="0.04 0.06 0.13 rg 0 828 595 14 re f\n";
            $s.=self::text('CRM',42,774,30,true,'0 0.66 0.86').self::text('TECHNOLOGY',116,777,11,true).self::text('CRM Technology (Pty) Ltd',42,750,9,false,'0.38 0.43 0.50');
            $s.=self::text('QUOTATION',393,780,20,true).self::text($quote['number'],393,759,9).self::text(strtoupper($quote['status']).' / FOR REVIEW',393,742,8,true,'0.20 0.34 1');
            $s.=self::rule(722).self::text('PREPARED FOR',42,700,8,true,'0.38 0.43 0.50');
            $s.=self::text(mb_substr((string)($customer['name']??'Customer'),0,55),42,682,12,true).self::text(mb_substr((string)($customer['email']??''),0,65),42,666,9);
            $s.=self::text('ISSUED',393,700,8,true,'0.38 0.43 0.50').self::text(substr($quote['created_at'],0,10),463,700,9);
            $s.=self::text('VALID UNTIL',393,680,8,true,'0.38 0.43 0.50').self::text(substr($quote['expires_at'],0,10),463,680,9);
            $s.="0.96 0.97 0.99 rg 42 619 511 30 re f\n";
            $s.=self::text('PRODUCT / CONFIGURATION',52,630,8,true).self::text('QTY',338,630,8,true).self::text('UNIT PRICE',381,630,8,true).self::text('LINE TOTAL',474,630,8,true);
            $y=601;
            foreach($rows as [$item,$lines,$rowHeight]){
                foreach($lines as $i=>$line)$s.=self::text($line,52,$y-$i*13,9,$i===0);
                $s.=self::text((string)$item['quantity'],342,$y,9).self::text(self::money((int)$item['unit_price_cents']),381,$y,9).self::text(self::money((int)$item['line_total_cents']),474,$y,9,true);
                if($item['sku']!=='')$s.=self::text('SKU: '.mb_substr($item['sku'],0,45),52,$y-count($lines)*13-3,7,false,'0.38 0.43 0.50');
                $y-=$rowHeight;$s.=self::rule($y+13);
            }
            if($pageNo===count($chunks)-1){
                $s.="0.04 0.06 0.13 rg 328 154 225 54 re f\n";
                $s.=self::text(!empty($customer['discount_cents'])?'ITEMS TOTAL AFTER DISCOUNT':'ITEMS TOTAL',342,188,8,true,'0.72 0.77 0.85').self::text(self::money((int)$quote['total_cents']),342,168,17,true,'1 1 1');
                if(!empty($customer['discount_cents']))$s.=self::text('Promotion discount: -'.self::money((int)$customer['discount_cents']),42,204,9,true);$s.=self::text('Delivery and VAT treatment require confirmation.',42,186,9).self::text('Prices and availability are subject to merchant review.',42,170,8);
            }else $s.=self::text('CONTINUED ON THE NEXT PAGE',42,172,8,true,'0.20 0.34 1');
            $s.=self::rule(127).self::text('A clear next step.',42,107,11,true).self::text('This draft requires CRM approval before it becomes a commercial offer.',42,91,9);
            if(getenv('APP_ENV')!=='production')$s.=self::text('DEMONSTRATION QUOTATION - NO PAYMENT IS REQUESTED',42,73,8,true,'0.20 0.34 1');
            $s.=self::text('CRM TECHNOLOGY',42,39,7,true,'0.38 0.43 0.50').self::text('PAGE '.($pageNo+1).' OF '.count($chunks),483,39,7,false,'0.38 0.43 0.50');
            $objects[$id]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId]='<< /Length '.strlen($s)." >>\nstream\n".$s."\nendstream";
        }
        $objects[2]='<< /Type /Pages /Count '.count($chunks).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);
        $out="%PDF-1.4\n";$offsets=[];foreach($objects as $id=>$obj){$offsets[$id]=strlen($out);$out.="$id 0 obj\n$obj\nendobj\n";}$start=strlen($out);$out.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";foreach($objects as $id=>$obj)$out.=sprintf('%010d 00000 n ',$offsets[$id])."\n";$out.='trailer << /Size '.(count($objects)+1).' /Root 1 0 R >>'."\nstartxref\n$start\n%%EOF";return $out;
    }
}
