<?php

namespace Drupal\thron\Integration;

use Drupal\thron\Exception\AppTokenExpiredException;

class Thronintegration_HTTP {

  private static function getHeadersFromResponse($headerContent) {
    $headers = [];
    // Split the string on every "double" new line.
    $arrRequests = explode("\r\n\r\n", $headerContent);
    // Loop of response headers. The "count() -1" is to
    //avoid an empty row for the extra line break before the body of the response.
    for ($index = 0; $index < count($arrRequests) - 1; $index++) {

      foreach (explode("\r\n", $arrRequests[$index]) as $i => $line) {
        if ($i === 0) {
          $headers['http_code'] = $line;
        }
        else {
          list ($key, $value) = explode(': ', $line);
          $headers[$key] = $value;
        }
      }
    }

    return $headers;
  }

  /**
   * @param $method
   * @param $url
   * @param bool $data
   * @param bool $headers
   * @param bool $returnResponseHeaders
   *
   * @return array|bool|string
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  private static function callCurl($method, $url, $data = FALSE, $headers = FALSE, $returnResponseHeaders = FALSE) {
    try {
      set_time_limit(30);
      /* echo "*******************************\n";
        var_dump($method);
        var_dump($url);
        var_dump($data);
        var_dump($headers);
        echo "*******************************\n"; */

      $curlHeaders = [];

      if ($headers) {
        // does the headers array already carry the X-THRONAPP key?
        if (!isset($headers["X-THRONAPP"]) || trim($headers["X-THRONAPP"]) == "") {
          $headers["X-THRONAPP"] = "marketplace";
        }

        // map associative array to "header: value" couples
        foreach ($headers as $key => $value) {
          // if the method is JSON_POST we can safely skip the "content-type" header
          if ($method == "JSON_POST" && $key == "Content-Type") {
            // skip this header
          }
          else {
            array_push($curlHeaders, "$key: $value");
          }
        }
      }
      else {
        array_push($curlHeaders, "X-THRONAPP: marketplace");
      }

      $file = FALSE;

      $curl = curl_init();
      //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
      //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_ENCODING, '');

      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
      if ($returnResponseHeaders) {
        curl_setopt($curl, CURLOPT_HEADER, TRUE);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, FALSE);
      }

      switch ($method) {
        case "POST":
          curl_setopt($curl, CURLOPT_POST, 1);
          if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
          }
          else {
            array_push($curlHeaders, "Content-Length: 0");
          }

          break;
        case "FORM_POST":
          curl_setopt($curl, CURLOPT_POST, 1);
          if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
          }
          else {
            array_push($curlHeaders, "Content-Length: 0");
          }
          break;
        case "POST_WITH_GET_PARAMS":
          array_push($curlHeaders, "Content-Type: application/x-www-form-urlencoded");

          curl_setopt($curl, CURLOPT_POST, 1);
          curl_setopt($curl, CURLOPT_POSTFIELDS, []);

          if ($data) {
            $url = sprintf("%s?%s", $url, http_build_query($data));
          }

          //array_push($curlHeaders, "Content-Length: 0");
          break;
        case "JSON_POST":
          array_push($curlHeaders, "Content-Type: application/json");
          curl_setopt($curl, CURLOPT_POST, 1);
          if ($data) {
            $encoded = json_encode($data);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encoded);
          }
          else {
            array_push($curlHeaders, "Content-Length: 0");
          }
          break;
        case "PUT":
          curl_setopt($curl, CURLOPT_PUT, 1);
          break;
        case "JSON_PUT":
          array_push($curlHeaders, "Content-Type: application/json");
          curl_setopt($curl, CURLOPT_PUT, 1);
          if ($data) {
            $dataToBePassed = json_encode($data);
            $file = tmpfile();
            fwrite($file, $dataToBePassed);
            fseek($file, 0);
            curl_setopt($curl, CURLOPT_INFILE, $file);
            curl_setopt($curl, CURLOPT_INFILESIZE, strlen($dataToBePassed));
          }
          /*curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
          curl_setopt($curl, CURLOPT_POSTFIELDS,json_encode($data));*/
          break;
        case "DELETE":
          curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
          if ($data) {
            $url = sprintf("%s?%s", $url, http_build_query($data));
          }
          //echo "deleting from URL: $url<br />";
          break;
        case "PATCH":
          curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
          if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
          }
          //echo "deleting from URL: $url<br />";
          break;

        case "HEAD":
          curl_setopt($curl, CURLOPT_NOBODY, TRUE);
          break;

        default:
          if ($data) {
            $url = sprintf("%s?%s", $url, http_build_query($data));
          }
      }

      if ($headers) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $curlHeaders);
      }


      curl_setopt($curl, CURLOPT_URL, $url);
      set_time_limit(900);
      ini_set("memory_limit", "512M");
      ini_set('max_execution_time', 900);

      $res = curl_exec($curl);
      $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      
      if ($file) {
        try {
          fclose($file);
        } catch (\Exception $fe) {

        }
      }

      if ($returnResponseHeaders) {
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($res, 0, $header_size);
        $body = substr($res, $header_size);
        $headers = Thronintegration_HTTP::getHeadersFromResponse($header);
        if ($method == "HEAD") {
          return [$headers, FALSE];
        }
        else {
          return [$headers, $body];
        }
      }

      curl_close($curl);

      if ($httpcode == 403) {
        throw new \Exception('HTTP_403');
      }

      if ($method == "HEAD") {
        return $headers;
      }
      else {
        return $res;
      }
    } catch (\Exception $ex) {
      if ($ex->getMessage() == 'HTTP_403') {
        throw new AppTokenExpiredException();
      }

      return FALSE;
    }
  }

  /**
   * @param $method
   * @param $url
   * @param bool $data
   * @param bool $headers
   * @param bool $returnResponseHeaders
   *
   * @return array|bool|string
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  public static function doHTTP($method, $url, $data = FALSE, $headers = FALSE, $returnResponseHeaders = FALSE) {
    return self::callCurl($method, $url, $data, $headers, $returnResponseHeaders);
  }

  public static function mergeHeaders($headers, $additionalHeaders = FALSE) {
    if (!$additionalHeaders || !is_array($additionalHeaders) || count($additionalHeaders) == 0) {
      return $headers;
    }

    return array_merge($headers, $additionalHeaders);
  }

}
