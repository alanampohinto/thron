<?php

namespace Drupal\thron\Integration;

/*
 * Generic utilities
 */

class Thronintegration_Utils {

  /*
   * Array utilities: calculate the difference between two arrays in a recursive fashion
   */

  public static function getExtensionFromMimeType($mime) {
      $mime_map = [
          'video/3gpp2'                                                               => '3g2',
          'video/3gp'                                                                 => '3gp',
          'video/3gpp'                                                                => '3gp',
          'application/x-compressed'                                                  => '7zip',
          'audio/x-acc'                                                               => 'aac',
          'audio/ac3'                                                                 => 'ac3',
          'application/postscript'                                                    => 'ai',
          'audio/x-aiff'                                                              => 'aif',
          'audio/aiff'                                                                => 'aif',
          'audio/x-au'                                                                => 'au',
          'video/x-msvideo'                                                           => 'avi',
          'video/msvideo'                                                             => 'avi',
          'video/avi'                                                                 => 'avi',
          'application/x-troff-msvideo'                                               => 'avi',
          'application/macbinary'                                                     => 'bin',
          'application/mac-binary'                                                    => 'bin',
          'application/x-binary'                                                      => 'bin',
          'application/x-macbinary'                                                   => 'bin',
          'image/bmp'                                                                 => 'bmp',
          'image/x-bmp'                                                               => 'bmp',
          'image/x-bitmap'                                                            => 'bmp',
          'image/x-xbitmap'                                                           => 'bmp',
          'image/x-win-bitmap'                                                        => 'bmp',
          'image/x-windows-bmp'                                                       => 'bmp',
          'image/ms-bmp'                                                              => 'bmp',
          'image/x-ms-bmp'                                                            => 'bmp',
          'application/bmp'                                                           => 'bmp',
          'application/x-bmp'                                                         => 'bmp',
          'application/x-win-bitmap'                                                  => 'bmp',
          'application/cdr'                                                           => 'cdr',
          'application/coreldraw'                                                     => 'cdr',
          'application/x-cdr'                                                         => 'cdr',
          'application/x-coreldraw'                                                   => 'cdr',
          'image/cdr'                                                                 => 'cdr',
          'image/x-cdr'                                                               => 'cdr',
          'zz-application/zz-winassoc-cdr'                                            => 'cdr',
          'application/mac-compactpro'                                                => 'cpt',
          'application/pkix-crl'                                                      => 'crl',
          'application/pkcs-crl'                                                      => 'crl',
          'application/x-x509-ca-cert'                                                => 'crt',
          'application/pkix-cert'                                                     => 'crt',
          'text/css'                                                                  => 'css',
          'text/x-comma-separated-values'                                             => 'csv',
          'text/comma-separated-values'                                               => 'csv',
          'application/vnd.msexcel'                                                   => 'csv',
          'application/x-director'                                                    => 'dcr',
          'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
          'application/x-dvi'                                                         => 'dvi',
          'message/rfc822'                                                            => 'eml',
          'application/x-msdownload'                                                  => 'exe',
          'video/x-f4v'                                                               => 'f4v',
          'audio/x-flac'                                                              => 'flac',
          'video/x-flv'                                                               => 'flv',
          'image/gif'                                                                 => 'gif',
          'application/gpg-keys'                                                      => 'gpg',
          'application/x-gtar'                                                        => 'gtar',
          'application/x-gzip'                                                        => 'gzip',
          'application/mac-binhex40'                                                  => 'hqx',
          'application/mac-binhex'                                                    => 'hqx',
          'application/x-binhex40'                                                    => 'hqx',
          'application/x-mac-binhex40'                                                => 'hqx',
          'text/html'                                                                 => 'html',
          'image/x-icon'                                                              => 'ico',
          'image/x-ico'                                                               => 'ico',
          'image/vnd.microsoft.icon'                                                  => 'ico',
          'text/calendar'                                                             => 'ics',
          'application/java-archive'                                                  => 'jar',
          'application/x-java-application'                                            => 'jar',
          'application/x-jar'                                                         => 'jar',
          'image/jp2'                                                                 => 'jp2',
          'video/mj2'                                                                 => 'jp2',
          'image/jpx'                                                                 => 'jp2',
          'image/jpm'                                                                 => 'jp2',
          'image/jpeg'                                                                => 'jpeg',
          'image/pjpeg'                                                               => 'jpeg',
          'application/x-javascript'                                                  => 'js',
          'application/json'                                                          => 'json',
          'text/json'                                                                 => 'json',
          'application/vnd.google-earth.kml+xml'                                      => 'kml',
          'application/vnd.google-earth.kmz'                                          => 'kmz',
          'text/x-log'                                                                => 'log',
          'audio/x-m4a'                                                               => 'm4a',
          'audio/mp4'                                                                 => 'm4a',
          'application/vnd.mpegurl'                                                   => 'm4u',
          'audio/midi'                                                                => 'mid',
          'application/vnd.mif'                                                       => 'mif',
          'video/quicktime'                                                           => 'mov',
          'video/x-sgi-movie'                                                         => 'movie',
          'audio/mpeg'                                                                => 'mp3',
          'audio/mpg'                                                                 => 'mp3',
          'audio/mpeg3'                                                               => 'mp3',
          'audio/mp3'                                                                 => 'mp3',
          'video/mp4'                                                                 => 'mp4',
          'video/mpeg'                                                                => 'mpeg',
          'application/oda'                                                           => 'oda',
          'audio/ogg'                                                                 => 'ogg',
          'video/ogg'                                                                 => 'ogg',
          'application/ogg'                                                           => 'ogg',
          'application/x-pkcs10'                                                      => 'p10',
          'application/pkcs10'                                                        => 'p10',
          'application/x-pkcs12'                                                      => 'p12',
          'application/x-pkcs7-signature'                                             => 'p7a',
          'application/pkcs7-mime'                                                    => 'p7c',
          'application/x-pkcs7-mime'                                                  => 'p7c',
          'application/x-pkcs7-certreqresp'                                           => 'p7r',
          'application/pkcs7-signature'                                               => 'p7s',
          'application/pdf'                                                           => 'pdf',
          'application/octet-stream'                                                  => 'pdf',
          'application/x-x509-user-cert'                                              => 'pem',
          'application/x-pem-file'                                                    => 'pem',
          'application/pgp'                                                           => 'pgp',
          'application/x-httpd-php'                                                   => 'php',
          'application/php'                                                           => 'php',
          'application/x-php'                                                         => 'php',
          'text/php'                                                                  => 'php',
          'text/x-php'                                                                => 'php',
          'application/x-httpd-php-source'                                            => 'php',
          'image/png'                                                                 => 'png',
          'image/x-png'                                                               => 'png',
          'application/powerpoint'                                                    => 'ppt',
          'application/vnd.ms-powerpoint'                                             => 'ppt',
          'application/vnd.ms-office'                                                 => 'ppt',
          'application/msword'                                                        => 'ppt',
          'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
          'application/x-photoshop'                                                   => 'psd',
          'image/vnd.adobe.photoshop'                                                 => 'psd',
          'audio/x-realaudio'                                                         => 'ra',
          'audio/x-pn-realaudio'                                                      => 'ram',
          'application/x-rar'                                                         => 'rar',
          'application/rar'                                                           => 'rar',
          'application/x-rar-compressed'                                              => 'rar',
          'audio/x-pn-realaudio-plugin'                                               => 'rpm',
          'application/x-pkcs7'                                                       => 'rsa',
          'text/rtf'                                                                  => 'rtf',
          'text/richtext'                                                             => 'rtx',
          'video/vnd.rn-realvideo'                                                    => 'rv',
          'application/x-stuffit'                                                     => 'sit',
          'application/smil'                                                          => 'smil',
          'text/srt'                                                                  => 'srt',
          'image/svg+xml'                                                             => 'svg',
          'application/x-shockwave-flash'                                             => 'swf',
          'application/x-tar'                                                         => 'tar',
          'application/x-gzip-compressed'                                             => 'tgz',
          'image/tiff'                                                                => 'tiff',
          'text/plain'                                                                => 'txt',
          'text/x-vcard'                                                              => 'vcf',
          'application/videolan'                                                      => 'vlc',
          'text/vtt'                                                                  => 'vtt',
          'audio/x-wav'                                                               => 'wav',
          'audio/wave'                                                                => 'wav',
          'audio/wav'                                                                 => 'wav',
          'application/wbxml'                                                         => 'wbxml',
          'video/webm'                                                                => 'webm',
          'audio/x-ms-wma'                                                            => 'wma',
          'application/wmlc'                                                          => 'wmlc',
          'video/x-ms-wmv'                                                            => 'wmv',
          'video/x-ms-asf'                                                            => 'wmv',
          'application/xhtml+xml'                                                     => 'xhtml',
          'application/excel'                                                         => 'xl',
          'application/msexcel'                                                       => 'xls',
          'application/x-msexcel'                                                     => 'xls',
          'application/x-ms-excel'                                                    => 'xls',
          'application/x-excel'                                                       => 'xls',
          'application/x-dos_ms_excel'                                                => 'xls',
          'application/xls'                                                           => 'xls',
          'application/x-xls'                                                         => 'xls',
          'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
          'application/vnd.ms-excel'                                                  => 'xlsx',
          'application/xml'                                                           => 'xml',
          'text/xml'                                                                  => 'xml',
          'text/xsl'                                                                  => 'xsl',
          'application/xspf+xml'                                                      => 'xspf',
          'application/x-compress'                                                    => 'z',
          'application/x-zip'                                                         => 'zip',
          'application/zip'                                                           => 'zip',
          'application/x-zip-compressed'                                              => 'zip',
          'application/s-compressed'                                                  => 'zip',
          'multipart/x-zip'                                                           => 'zip',
          'text/x-scriptzsh'                                                          => 'zsh',
      ];
  
      return isset($mime_map[$mime]) ? $mime_map[$mime] : false;
  }
  
  public static function arrayRecursiveDiff($aArray1, $aArray2) {
    $aReturn = [];
    try {
      if ($aArray1 && $aArray2) {
        foreach ($aArray1 as $mKey => $mValue) {
          if (array_key_exists($mKey, $aArray2)) {
            if (is_array($mValue)) {
              $aRecursiveDiff = Thronintegration_Utils::arrayRecursiveDiff($mValue, $aArray2[$mKey]);
              if (count($aRecursiveDiff)) {
                $aReturn[$mKey] = $aRecursiveDiff;
              }
            }
            else {
              if ($mValue != $aArray2[$mKey]) {
                $aReturn[$mKey] = $mValue;
              }
            }
          }
          else {
            $aReturn[$mKey] = $mValue;
          }
        }
      }
    } catch (Exception $ex) {
      // TODO notify this error?
    }

    return $aReturn;
  }

  /*
   * Strings utilities: "starts with"
   */

  public static function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
  }

  /*
   * Strings utilities: "ends with"
   */

  public static function endsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || strpos($haystack, $needle, strlen($haystack) - strlen($needle)) !== FALSE;
  }

  /*
   * Returns true if a string is null or empty (or made only of spaces)
   */

  public static function IsNullOrEmptyString($text) {
    return (!isset($text) || trim($text) === '');
  }

  /*
   * Truncates array to a certain number of elements
   */

  public static function truncateArray($truncateAt, $arr) {
    array_splice($arr, $truncateAt, (count($arr) - $truncateAt));
    return $arr;
  }

  /*
   * Removes the quotes from a string
   */

  public static function stripQuotes($text) {
    $unquoted = preg_replace('/^(\'(.*)\'|"(.*)")$/', '$2$3', $text);
    return $unquoted;
  }

  /*
   * Generates a "random" string with arbitrary length
   */

  public static function generateRandomString($length = 5) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = $characters[rand(10, $charactersLength - 1)];

    for ($i = 1; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  /*
   * Detects if this is a valid token; false otherwise
   */

  public static function isValidToken($token) {
    if (!trim($token)) {
      return FALSE;
    }
    return preg_match("/^[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}$/i", $token);
  }

  public static function httpGetContent($url, $headers = FALSE) {
    try {
      //echo "Reading the URL $url...<br />";
      $getContentResp = Thronintegration_HTTP::doHTTP("GET", $url, FALSE, $headers);

      if (!$getContentResp) {
        throw new Exception(sprintf("Cannot read the url %s!", $url));
      }
      return $getContentResp;
    } catch (Exception $ex) {
      // TODO notify!
      return FALSE;
    }
  }

}
