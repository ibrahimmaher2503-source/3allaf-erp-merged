<?php

namespace App\Modules\Pricing\Services;

use InvalidArgumentException;

final class BarcodeSvgRenderer
{
    private const CODE128 = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112'
    ];

    public function render(string $value, string $symbology = 'auto', int $height = 64): string
    {
        $value = trim($value);
        if ($value === '') throw new InvalidArgumentException(__('A barcode value is required.'));
        if ($symbology === 'ean13' && ! $this->validEan13($value)) throw new InvalidArgumentException(__('EAN-13 requires a valid 13-digit GTIN checksum.'));
        if (($symbology === 'ean13' || ($symbology === 'auto' && preg_match('/^\d{13}$/', $value))) && $this->validEan13($value)) {
            return $this->ean13($value, $height);
        }
        if (! preg_match('/^[\x20-\x7E]{1,48}$/', $value)) throw new InvalidArgumentException(__('The selected barcode cannot be encoded as EAN-13 or Code 128.'));
        return $this->code128($value, $height);
    }

    public function validEan13(string $value): bool
    {
        if (! preg_match('/^\d{13}$/', $value)) return false;
        $sum = 0;
        for ($i = 0; $i < 12; $i++) $sum += (int) $value[$i] * ($i % 2 === 0 ? 1 : 3);
        return (10 - ($sum % 10)) % 10 === (int) $value[12];
    }

    private function code128(string $value, int $height): string
    {
        $codes = [104]; $checksum = 104;
        foreach (str_split($value) as $index => $char) { $code = ord($char) - 32; $codes[] = $code; $checksum += $code * ($index + 1); }
        $codes[] = $checksum % 103; $codes[] = 106;
        $bars = ''; $x = 10;
        foreach ($codes as $code) foreach (str_split(self::CODE128[$code]) as $i => $width) { $w=(int)$width*2; if ($i%2===0) $bars.='<rect x="'.$x.'" y="2" width="'.$w.'" height="'.$height.'"/>'; $x += $w; }
        return $this->svg($value, $bars, $x + 10, $height);
    }

    private function ean13(string $value, int $height): string
    {
        $l=['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'];
        $g=['0100111','0110011','0011011','0100001','0011101','0111001','0000101','0010001','0001001','0010111'];
        $r=['1110010','1100110','1101100','1000010','1011100','1001110','1010000','1000100','1001000','1110100'];
        $parity=['LLLLLL','LLGLGG','LLGGLG','LLGGGL','LGLLGG','LGGLLG','LGGGLL','LGLGLG','LGLGGL','LGGLGL'];
        $bits='101'; $p=$parity[(int)$value[0]];
        for($i=1;$i<=6;$i++) $bits.=($p[$i-1]==='L'?$l:$g)[(int)$value[$i]];
        $bits.='01010'; for($i=7;$i<=12;$i++) $bits.=$r[(int)$value[$i]]; $bits.='101';
        $bars=''; foreach(str_split($bits) as $i=>$bit) if($bit==='1') $bars.='<rect x="'.(10+$i*2).'" y="2" width="2" height="'.$height.'"/>';
        return $this->svg($value,$bars,210,$height);
    }

    private function svg(string $value,string $bars,int $width,int $height): string
    {
        return '<svg class="barcode-svg" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="'.e($value).'" viewBox="0 0 '.$width.' '.($height+20).'" preserveAspectRatio="none"><g fill="#000">'.$bars.'</g><text x="50%" y="'.($height+16).'" text-anchor="middle" font-family="monospace" font-size="12">'.e($value).'</text></svg>';
    }
}
