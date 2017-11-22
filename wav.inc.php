<?php
/**
 * WaveFileReader; a simple class to read/parse WAVE file
 * (c)2012 Rob Janssen / https://github.com/RobThree
 * 
 * Based on https://ccrma.stanford.edu/courses/422/projects/WaveFormat/
 * 
 * USAGE:
 * 
 * $wav = WaveFileReader::ReadFile('/path/to/test.wav');
 * var_dump($wav);
 * var_dump(WaveFileReader::GetAudioFormat($wav['subchunk1']['audioformat']));
 */
class WaveFileReader {
    
    private static $HEADER_LENGTH = 44;    //Header size is at least 44 bytes (and possibly more...)

    /**
    * ReadFile reads the headers (and optionally the data) from a wave file
    * 
    * @param    string  $filename   The wave file to read the header from
    * @param    bool    $readdata   (OPTIONAL) Pass TRUE to read the actual audio data from the file; defaults to FALSE
    * 
    * @return   mixed               Information parsed from the file; FALSE when file doesn't exists or not readable
    */
    public static function ReadFile($filename, $readdata = false) {
        //Make sure file is readable and exists        
        if (is_readable($filename)) {
            $filesize = filesize($filename);
            
            //Make sure filesize is sane; e.g. at least the headers should be able to fit in it
            if ($filesize<self::$HEADER_LENGTH)
                return false;

            //Read the header and stuff all info in an array            
            $handle = fopen($filename, 'rb');
            $riff = array(
                'chunkid'       => self::readString($handle, 4),
                'chunksize'     => self::readLong($handle),
                'format'        => self::readString($handle, 4)
            );
                    
            $fmt = array(
                'id'            => self::readString($handle, 4),
                'size'          => self::readLong($handle),
                'audioformat'   => self::readWord($handle),
                'numchannels'   => self::readWord($handle),
                'samplerate'    => self::readLong($handle),
                'byterate'      => self::readLong($handle),
                'blockalign'    => self::readWord($handle),
                'bitspersample' => self::readWord($handle),
                'extraparamsize'=> 0,
                'extraparams'   => null
            );

            if ($fmt['audioformat'] != 1) {  //NON-PCM?
                $fmt['extraparamsize'] = self::readWord($handle);
                $fmt['extraparams'] = self::readString($handle, $fmt['extraparamsize']);
            }

            $data = array(
                'id'            => self::readString($handle, 4),
                'size'          => self::readLong($handle),
                'data'          => null
            );
   
            //Do we need to read the data and, if so, does the specified size in subchunk2 match the actual size?
            $totalhdrssize = self::$HEADER_LENGTH + $fmt['extraparamsize'];
            if ($readdata && ($filesize - $totalhdrssize) == $data['size'])
                $data['data'] = fread($handle, $filesize - $totalhdrssize);

            //...aaaaand we're done!
            fclose($handle);
            
            //Do some checks...
            if (($fmt['numchannels'] *  $fmt['samplerate'] * floor($fmt['bitspersample'] / 8)) != $fmt['byterate'])
                throw new Exception('Byterate field mismatch');

            if (($fmt['numchannels'] *  floor($fmt['bitspersample'] / 8)) != $fmt['blockalign'])
                throw new Exception('Blockalign field mismatch');
            
            if ($riff['chunkid'] != 'RIFF')
                throw new Exception('RIFF chunk ID invalid');

            if ($fmt['id'] != 'fmt ')
                throw new Exception('SubChunk1 chunk ID invalid');
          
            if ($data['id'] != 'data')
                throw new Exception('SubChunk2 chunk ID invalid');

            return array('header' => $riff, 'subchunk1' => $fmt, 'subchunk2' => $data);
        }
        //File is not readable or doesn't exist
        return false;
    }
    
    /**
    * Returns a string representation of an audioformat "id"
    * 
    * @param    int     $audiofmtid The audioformat "id" to get the string representation of
    * 
    * @return   string              The audioformat as a string (e.g. "PCM", "ALAW", "ULAW", ...), NULL when an
    *                               unknown audioformat "id" is passed
    */
    public static function GetAudioFormat($audiofmtid) {
        switch($audiofmtid) {
            case 1:
                return 'PCM';
            case 2:
                return 'Microsoft ADPCM';
            case 6:
                return 'ITU G.711 a-law';
            case 7:
                return 'ITU G.711 µ-law';
            case 17:
                return 'IMA ADPCM';
            case 20:
                return 'ITU G.723 ADPCM (Yamaha)';
            case 49:
                return 'GSM 6.10';
            case 64:
                return 'ITU G.721 ADPCM';
            case 80:
                return 'MPEG';
            case 65536:
                return 'Experimental';
            default:
                return null;
        }
    }
    
    /**
    * Reads a string from the specified file handle
    * 
    * @param    int     $handle     The filehandle to read the string from
    * @param    int     $length     The number of bytes to read
    * 
    * @return   string              The string read from the file
    */
    private static function readString($handle, $length) {
        return self::readUnpacked($handle, 'a*', $length);
    }

    /**
    * Reads a 32bit unsigned integer from the specified file handle
    * 
    * @param    int     $handle     The filehandle to read the 32bit unsigned integer from
    * 
    * @return   int                 The 32bit unsigned integer read from the file
    */
    private static function readLong($handle) {
        return self::readUnpacked($handle, 'V', 4);
    }

    /**
    * Reads a 16bit unsigned integer from the specified file handle
    * 
    * @param    int     $handle     The filehandle to read the 16bit unsigned integer from
    * 
    * @return   int                 The 16bit unsigned integer read from the file
    */
    private static function readWord($handle) {
        return self::readUnpacked($handle, 'v', 2);
    }
    
    /**
    * Reads the specified number of bytes from a specified file handle and unpacks it accoring to the specified type
    * 
    * @param    int     $handle     The filehandle to read the data from
    * @param    int     $type       The type of data being read (see PHP's Pack() documentation)
    * @param    int     $length     The number of bytes to read
    * 
    * @return   mixed               The unpacked data read from the file
    */
    private static function readUnpacked($handle, $type, $length) {
        $r = unpack($type, fread($handle, $length));
        return array_pop($r);
    }
}