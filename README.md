# PHP WAV spectrum
Draw image spectrum of WAV file.

## Usage

```$file_name = __DIR__.'/orig.wav';
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
```

## Result

Spectrum image stored to file ./php_spectrum.png
