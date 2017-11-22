<?php

class CSampler {

    private $hanning;
    private $palette;
    private $gd;

    function __construct() {
        $this->hanning = $this->obtainHanWindow(2048);
    }

    function makeSpectrum($data, $frequency) {
        $shift_value = $frequency / 100;
        $width = ceil(count($data) / $shift_value);
        $height = 1024;
        $this->gd = imagecreatetruecolor($width, $height);
        // create pallete
        $this->createPalette($this->gd);
        $x = 0;
        for($i = 0; $i < count($data) - 2048 - $shift_value; $i += $shift_value) {
            $buffer = array_slice($data, $i, 2048);
            // applying hanning window
            $doubleAudioData = range(0, count($buffer) - 1);
            for($j = 0; $j < count($buffer); $j++) {
                if(is_numeric($buffer[$j])) {
                    $doubleAudioData[$j] = $buffer[$j] * $this->hanning[$j];
                }
            }
            $fft = FFT::magnitude($doubleAudioData);
            $spectrum = $this->calcSpectrum($fft, $height);
            foreach($spectrum as $y => $value) {
                $color = $this->palette[$value];
                imagesetpixel($this->gd, $x, $y, $color);
            }
            $x++;
        }
        imagepng($this->gd, __DIR__.'/php_spectrum.png');
    }

    private function calcSpectrum(array $fft, int $size) {
        $spectrum = range(0, $size - 1);
        $block_norm = 0.5;
        for($j = 1; $j < $size; $j++) {
            $magnitude = $fft[$j];
            $dBfs = 10 * log10($magnitude * $block_norm);
            $dBfs = $dBfs * 4 - 43;
            $dBfs = min([255, max([0, (int)$dBfs])]);
            $y = $size - $j;
            $spectrum[$y] = $dBfs;
        }
        return $spectrum;
    }

    private function obtainHanWindow(int $size) {
        // Size must be Odd and Positive integer
        $dHan = [];
        for ($i = 0; $i < $size; $i++) {
            $Pi2DivSize = 2.0 * pi() * $i / $size;
            $dHan[$i] = 1.0 - cos($Pi2DivSize);
        }
        return $dHan;
    }

    private function createPalette($gd) {
        $this->palette = [];
        for($i = 0; $i < 256; $i++) {
            $r = $i;
            $g = $i > 100 ? (($i - 100) / 156) * 255 : 0;
            $b = $g;
            $this->palette[$i] = imagecolorallocate($gd, $r, $g, $b);
        }
    }

}

class FFT {

    public static function magnitude(array $data) {
        list($real, $im) = static::run($data);
        $count = count($real);
        for ($i = 0; $i < $count; $i++) {
            $output[$i] = sqrt(pow($real[$i], 2) + pow($im[$i],2));
        }
        return $output;
    }

    public static function run(array $input_real, array $input_im = null) {
        static $fft, $tr, $ti, $tlength;
        $length = count($input_real);
        // Use previously calculated values if length of data is the same as the last FFT we ran
        if ($tlength != $length) {
            $tlength = $length;
            $halfLength = $length / 2;
            for ($i = 0; $i < $halfLength; ++$i) {
                $p = $i / $length;
                $t = -2 * M_PI * $p;
                $tr[$i] = cos($t);
                $ti[$i] = sin($t);
            }
            $fft = function ($real, $im) use (&$fft, $tr, $ti, $tlength) {
                $length = count($real);
                $halfLength = $length / 2;
                if (!$im) $im = array_fill(0, $length, 0);
                if ($length < 2) return [$real, $im];
                for($i = 0; $i < $halfLength; ++$i) {
                    $even_real[$i] = $real[$i*2];
                    $odd_real[$i] = $real[($i*2)+1];
                    $even_im[$i] = $im[$i*2];
                    $odd_im[$i] = $im[($i*2)+1];
                }
                list($even_real, $even_im) = $fft($even_real, $even_im);
                list($odd_real, $odd_im) = $fft($odd_real, $odd_im);
                for ($i = 0; $i < $halfLength; ++$i) {
                    // $t = $t->cexp(); -- Precalculated for ~20% speed improvement
                    $p = $i / $length;
                    $t_real = $tr[$p * $tlength];
                    $t_im = $ti[$p * $tlength];
                    // $t = $t->mul($odd[$i]);
                    $t_real2 = ($t_real * $odd_real[$i]) - ($t_im * $odd_im[$i]);
                    $t_im2 = ($t_real * $odd_im[$i]) + ($t_im * $odd_real[$i]);
                    // $return[$i] = $even[$i]->add($t);
                    $return_real[$i] = $even_real[$i] + $t_real2;
                    $return_im[$i] = $even_im[$i] + $t_im2;
                    // $return[$i + $halfLength] = $even[$i]->sub($t);
                    $return_real[$i + $halfLength] = $even_real[$i] - $t_real2;
                    $return_im[$i + $halfLength] = $even_im[$i] - $t_im2;
                }
                return [$return_real, $return_im];
            };
        }
        return $fft($input_real, $input_im);
    }
}

$file_name = __DIR__.'/orig.wav';
include __DIR__.'/wav.inc.php';
$wavHeader = WaveFileReader::ReadFile($file_name, true);
$sampleRate = 44100;

foreach($wavHeader as $chunk) {
    if(array_key_exists('id', $chunk)) {
        if($chunk['id'] == 'fmt ') {
            $sampleRate = $chunk['samplerate'];
        } elseif($chunk['id'] == 'data') {
            $data = unpack('s*', $chunk['data']);
            $sampler = new CSampler;
            $sampler->makeSpectrum($data, $sampleRate);
        }
    }
}

// $f = substr(file_get_contents($file_name), 4096);
// $data = unpack('s*', $f);
// $data = array_values($data);
// $file_name = __DIR__.'/spectrum.dat';
// $data = explode(',', trim(str_replace(';', ',', file_get_contents($file_name)), ','));


