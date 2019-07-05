<?php

namespace Drupal\thron\Integration;

/*
 * Generic utilities
 */

class Thronintegration_Utils {

  /*
   * Array utilities: calculate the difference between two arrays in a recursive fashion
   */

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
