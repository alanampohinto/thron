<?php

namespace Drupal\thron\Integration;

use Drupal\thron\Exception\AppTokenExpiredException;
use function GuzzleHttp\Psr7\build_query;

define('THRON_RESULTS_PER_PAGE', 50);


/*
 * THRON API integration
 */

class Thronintegration_Api {

  private static $initiated = FALSE;

  public static $appConfigParams = NULL;

  public static $shortcodeParams = NULL;

  private static $protocol = "https:";

  private static $port = NULL;

  private static $domainNamingConvention = "%s-view.thron.com";

  /*
   * Get the THRON application endpoint
   */

  public static function getThronEndpoint($clientId, $module) {
    $protocol = Thronintegration_Api::$protocol;
    $port = Thronintegration_Api::$port;

    if (Thronintegration_Utils::IsNullOrEmptyString($clientId) || Thronintegration_Utils::IsNullOrEmptyString($module)) {
      return FALSE;
    }

    if(isset($port))
      $url = sprintf("%s//%s:%d/api/%s/resources/", $protocol, sprintf(Thronintegration_Api::$domainNamingConvention, $clientId), $port, $module);
    else
      $url = sprintf("%s//%s/api/%s/resources/", $protocol, sprintf(Thronintegration_Api::$domainNamingConvention, $clientId), $module);
    return $url;
  }

  /*
   * THRON integration: login as app (via apps/loginApp)
   */

  public static function performAppLogin($clientId, $appId, $appKey = FALSE) {
    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xadmin") . "apps/loginApp/$clientId";

      // perform login
      $data = [
        "appId" => $appId,
      ];

      if ($appKey && trim($appKey) != "") {
        $data["appKey"] = $appKey;
      }

      $tokenResp = Thronintegration_HTTP::doHTTP("FORM_POST", $url, $data);

      if (!$tokenResp) {
        // error while logging in... TODO notify!
        throw new \Exception(sprintf("Could not perform login on %s", $clientId));
      }

      // if this app can't disguise throw an error.
      $res = json_decode($tokenResp);

      if (!$res->app) {
        throw new \Exception(sprintf("%s: invalid app for %s!", $appId, $clientId));
      }
      return $res;
    } catch (\Exception $ex) {
      // TODO notify the error!
      return FALSE;
    }
  }

  /**
   * THRON integration: impersonate an user (via apps/su)
   * @param $clientId
   * @param $appId
   * @param $appUserTokenId
   * @param $appContentsOwner
   *
   * @return array|bool|string
   */
  public static function performAppSu($clientId, $appId, $appUserTokenId, $appContentsOwner) {
    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xadmin") . "apps/su";
      $data = [
        'clientId' => $clientId,
        'appId' => $appId,
        'appKey' => $appUserTokenId,
        'username' => $appContentsOwner,
      ];

      // perform login
      $suResp = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $data, ['Accept' => 'text/plain']);
      if (!$suResp) {
        // error while logging in... TODO notify!
        throw new \Exception(sprintf("%s: could not perform su on %s!", $appId, $clientId));
      }

      return $suResp;
    }
    catch (AppTokenExpiredException $ex) {
      // This method CAN't catch here - no such as session is considered
      // for this API METHOD -> therefore no further throwing
      return FALSE;
    }
    catch (\Exception $ex) {
      return FALSE;
    }
  }

  /*
   * THRON integration: invokes the content/search function for a specific content
   */
  public static function getContentDetailViaContentSearch($clientId, $token, $xcontentId, $anticache = FALSE, $divArea = FALSE) {
    if(!$divArea) $divArea = "320x0";
    $search_param = [
      "criteria" => [
        "ids" => [$content_id]
      ],
      "responseOptions" => [
        "returnDetailsFields" => ["locales", "author", "owner", "lastUpdate", "prettyIds", "playlistDetails", "userSpecificValues", "aclInfo", "publishingStatus", "highlights", "availableChannels", "linkedContent", "source", "itags", "linkedCategoryIds", "properties", "imetadata", "externalIds"]
      ]
    ];

    if($divArea && trim($divArea) != "")
      $search_param["responseOptions"]["thumbsOptions"] = [
        [
          "divArea" => $divArea,
          "id" => $divArea
        ]
      ];
     
    $resp = Thronintegration_Api::contentSearchLite($this->config->get('client_id'), $login_data['token'], $search_param);
    if(count($resp["items"])>0)
      return $resp["items"][0];
    return [];
  }

  /*
   * THRON integration: invokes the sync/export function
   *
   */
  public static function syncExport($clientId, $token, $categoryIds = [], $contentType = [], $pageSize = 0, $nextPage = NULL) {
    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "sync/export/$clientId";

      $data = new \stdClass();
      $data->criteria = new \stdClass();

      if ($pageSize) {
        $data->pageSize = $pageSize;
      }

      if (isset($nextPage)) {
        $data->nextPage = $nextPage;
      }

      if (is_array($contentType) && !empty($contentType)) {
        $data->criteria->contentType = $contentType;
      }

      if (is_array($categoryIds) && !empty($categoryIds)) {
        $data->criteria->linkedCategoryOp = new \stdClass();
        $data->criteria->linkedCategoryOp->cascade = TRUE;
        $data->criteria->linkedCategoryOp->linkedCategoryIds = $categoryIds;
      }

      $data->options = new \stdClass();
      $data->options->returnItags = TRUE;
      $data->options->returnLinkedCategories = TRUE;

      $headers = [
        "X-TOKENID" => $token,
        'clientId' => $clientId,
      ];

      $response = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $data, $headers);

      if (!$response) {
        throw new \Exception(sprintf("Cannot sync export with client %s!", $clientId));
      }

      return json_decode($response, FALSE);

    } catch (AppTokenExpiredException $ex) {
      throw $ex;
    } catch (\Exception $ex) {
      //var_dump($ex);
      throw $ex;
      exit();
    }
  }

  /*
   * THRON integration: invokes the sync/updatedContent function
   *
   */
  public static function syncUpdatedContent($clientId, $token, $fromDate, $toDate = NULL, $categoryIds = [], $contentType = [], $pageSize = 0, $nextPage = NULL) {
    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "sync/updatedContent/$clientId";

      $data = [
        'criteria' => [
          'fromDate' => $fromDate,
        ],
      ];

      if ($toDate) {
        $data['criteria']['toDate'] = $toDate;
      }

      if (isset($nextPage)) {
        $data['nextPage'] = $nextPage;
      }

      if (isset($pageSize)) {
        $data['pageSize'] = $pageSize;
      }

      if (is_array($categoryIds) && !empty($categoryIds)) {
        $data['criteria']['linkedCategoryOp'] = [
          'cascade' => TRUE,
          'linkedCategoryIds' => $categoryIds,
        ];
      }

      if (is_array($contentType) && !empty($contentType)) {
        $data['criteria']['contentType'] = $contentType;
      }

      $data['options'] = [
        'returnItags' => TRUE,
        'returnLinkedCategories' => TRUE,
      ];

      $headers = [
        "X-TOKENID" => $token,
        'clientId' => $clientId,
      ];

      $response = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $data, $headers);

      if (!$response) {
        throw new \Exception(sprintf("Cannot sync export with client %s!", $clientId));
      }

      return json_decode($response, FALSE);

    }
    catch (AppTokenExpiredException $ex) {
      throw $ex;
    }
    catch (\Exception $ex) {
      //var_dump($ex);
      throw $ex;
      exit();
    }
  }

  /**
   * Returns the contents specified by properties
   * @param $clientId
   * @param $token
   * @param $categoryId
   * @param $offset
   * @param int $numberOfresults
   * @param bool $contentType
   * @param bool $divArea
   * @param bool $tagSearch
   * @param bool $textSearch
   * @param bool $orderBy
   * @param bool $cascade
   * @param bool $locale
   *
   * @return mixed
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  public static function contentSearch($clientId, $token, $categoryId, $nextPageToken = NULL, $contentType = FALSE, $divArea = FALSE, $tagSearch = FALSE, $textSearch = FALSE, $orderBy = FALSE, $cascade = FALSE, $locale = FALSE) {
    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, 'xcontents') . 'content/search/'.$clientId;

      $body = new \stdClass();
      $body->criteria = new \stdClass();

      //var_dump($nextPageToken); exit();

      if($nextPageToken)
        $body->pageToken = $nextPageToken;

      if (!Thronintegration_Utils::IsNullOrEmptyString($categoryId)) {
        $body->criteria->linkedCategories = new \stdClass();
        $body->criteria->linkedCategories->haveAtLeastOne = [];
        $lcObj = new \stdClass();
        $lcObj->cascade = $cascade;
        $lcObj->id = $categoryId;
        array_push($body->criteria->linkedCategories->haveAtLeastOne, $lcObj);
      }

      if (is_array($contentType) && count($contentType)) {
        $body->criteria->contentType = $contentType;
      }
      else {
        if (!Thronintegration_Utils::IsNullOrEmptyString($contentType)) {
          $body->criteria->contentType = [$contentType];
        }
      }

      $body->responseOptions = new \stdClass();
      $body->responseOptions->returnDetailsFields = [
        "locales",
        "author",
        "owner",
        "lastUpdate",
        "prettyIds",
        "playlistDetails",
        "userSpecificValues",
        "aclInfo",
        "publishingStatus",
        "highlights",
        "availableChannels",
        "linkedContent",
        "source",
        "itags",
        "linkedCategoryIds",
        "properties",
        "imetadata",
        "externalIds"
      ];

      $body->responseOptions->orderBy = 'lastUpdate_d';
      if ($orderBy && !Thronintegration_Utils::IsNullOrEmptyString($orderBy)) {
        $body->responseOptions->orderBy = $orderBy;
      }

      $body->responseOptions->thumbsOptions = [];

      $askForDivArea = '320x0';
      if ($divArea && !Thronintegration_Utils::IsNullOrEmptyString($divArea)) {
        $askForDivArea = $divArea;
      }

      $thumbsOptsObj = new \stdClass();
      $thumbsOptsObj->divArea = $askForDivArea;
      $thumbsOptsObj->id = $askForDivArea;

      array_push($body->responseOptions->thumbsOptions, $thumbsOptsObj);
      
      if ($locale && !Thronintegration_Utils::IsNullOrEmptyString($locale)) {
        $body->criteria->lang = strtoupper($locale);
      }

      if ($textSearch && !Thronintegration_Utils::IsNullOrEmptyString($textSearch)) {
        $body->criteria->lang = strtoupper($locale);
        $body->criteria->lemma = new \stdClass();
        $body->criteria->lemma->text = $textSearch;
        $body->criteria->lemma->textMatch = 'EXACT_MATCH';
        $body->criteria->lemma->lang = strtoupper($locale);
      }

      if ($tagSearch && is_array($tagSearch) && count($tagSearch) > 0) {
        $body->criteria->itag = new \stdClass();
        $body->criteria->itag->haveAll = [];

        foreach ($tagSearch as $tag) {
          if (is_array($tag)) {
            $body->criteria->itag->haveAll[] = $tag;
          }
          elseif (!Thronintegration_Utils::IsNullOrEmptyString($tag)) {
            $body->criteria->itag->haveAll[] = [
              "cascade" => TRUE,
              "classificationId" => $tag, // TODO THIS IS DEFINITELY WRONG!
              'id' => $tag,
            ];
          }
        }
      }

      $contentSearchRes = Thronintegration_HTTP::doHTTP('JSON_POST', $url, $body, ['X-TOKENID' => $token]);
      
      if (!$contentSearchRes) {
        throw new \Exception(sprintf('Cannot find contents for client %s!', $clientId));
      }

      $data = json_decode($contentSearchRes, FALSE);
      $res['resultCode'] = 'OK';
      $res['total'] = $data->estimatedTotalResults;
      $res['contents'] = $data->items;
      $res['nextPageToken'] = $data->nextPageToken;
      $res['prevPageToken'] = $nextPageToken;

    }
    catch (AppTokenExpiredException $ex) {
      throw $ex;
    }
    catch (\Exception $ex) {
      $res['resultCode'] = 'ERROR';
      $res['errorDescription'] = $ex->getMessage();
    }
    return $res;
  }

  public static function appDetail($clientId, $token, $appId) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $param = [
        "clientId" => $clientId,
        "appId" => $appId,
      ];

      $url = Thronintegration_Api::getThronEndpoint($clientId, "xadmin") . "apps/appDetail";
      // update this app
      $appDetailResp = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $param, ["X-TOKENID" => $token]);
      /*var_dump($url);
                   var_dump($param);
                    var_dump($appDetailResp); exit;*/

      if (!$appDetailResp) {
        // error while updating app... TODO notify!
        throw new \Exception(sprintf("Could not get the app's detail on %s, appId %s", $clientId, $appId));
      }
      $appObj = json_decode($appDetailResp, TRUE);

      if (!isset($appObj["resultCode"])) {
        throw new \Exception("App $appId not found on client $clientId");
      }
      else {
        if ($appObj["resultCode"] == "APP_NOT_FOUND") {
          $res["status"] = "APP_NOT_FOUND";
        }
        else {
          if ($appObj["resultCode"] != "OK" || !isset($appObj["app"])) {
            $res["status"] = "NO";
            $res["errorDescription"] = $appObj["errorDescription"];
          }
          else {
            $res["status"] = "OK";
            $res["app"] = $appObj["app"];
          }
        }
      }
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }

    return $res;
  }

  private static function validateValidateUserRole($clientId, $token, $role) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("invalid clientId");
    }

    if (!isset($token) || Thronintegration_Utils::IsNullOrEmptyString($token) || !Thronintegration_Utils::isValidToken($token)) {
      throw new \Exception("invalid token");
    }

    if (!isset($role) || Thronintegration_Utils::IsNullOrEmptyString($role)) {
      throw new \Exception("invalid role");
    }
  }

  public static function validateUserRole($clientId, $token, $role, $forceValidateOnDifferentClientId = FALSE) {

    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      Thronintegration_Api::validateValidateUserRole($clientId, $token, $role);

      $param = [
        "role" => $role,
      ];
      global $clientConfig;

      if (!$forceValidateOnDifferentClientId) {
        $endPointCliendId = $clientConfig["clientId"];
      }
      else {
        $endPointCliendId = $clientId;
      }

      $url = Thronintegration_Api::getThronEndpoint($endPointCliendId, "xsso") . "accessmanager/validateRole/$clientId";

      // update this app
      $validateRoleResp = Thronintegration_HTTP::doHTTP("FORM_POST", $url, $param, ["X-TOKENID" => $token], TRUE);

      if (
        !$validateRoleResp ||
        !is_array($validateRoleResp) ||
        !isset($validateRoleResp[0]) ||
        !isset($validateRoleResp[0]["http_code"]) ||
        !strpos($validateRoleResp[0]["http_code"], "200")) {
        throw new \Exception("Role $role not present for the token check on $clientId");
      }

      $res["status"] = "OK";
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }

    return $res;
  }

  private static function validateVerifyAclRulesOnCategory($clientId, $token, $categoryId, $userName, $rules) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("invalid clientId");
    }

    if (!isset($token) || !Thronintegration_Utils::isValidToken($token)) {
      throw new \Exception("invalid token");
    }

    if (!isset($categoryId) || !Thronintegration_Utils::isValidToken($categoryId)) {
      throw new \Exception("invalid category ID");
    }

    if (!isset($userName) || Thronintegration_Utils::IsNullOrEmptyString($userName)) {
      throw new \Exception("invalid user name");
    }

    if (!isset($rules) || !is_array($rules) || count($rules) == 0) {
      throw new \Exception("invalid rules array");
    }
  }

  public static function verifyAclRulesOnCategory($clientId, $token, $categoryId, $userName, $rules, $performANDOnRules = FALSE) {
    try {
      Thronintegration_Api::validateVerifyAclRulesOnCategory($clientId, $token, $categoryId, $userName, $rules);
      $param = [
        "clientId" => $clientId,
        "rule" => [
          "targetObjId" => $categoryId,
          "targetObjClass" => "CATEGORY",
          "sourceAcl" => [
            "sourceObjId" => $userName,
            "sourceObjClass" => "USER",
            "rulesInverse" => $rules,
          ],
        ],
      ];
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "aclinverse/verifyAclRule";
      // check the rules
      $validateRoleResp = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $param, ["X-TOKENID" => $token]);
      if (!$validateRoleResp) {
        // error while checking the rules
        throw new \Exception(sprintf("Could not verify the ACL rules on %s, category %s", $clientId, $categoryId));
      }

      return json_decode($validateRoleResp, TRUE);
    } catch (\Exception $ex) {
      // TODO notify this error
      return FALSE;
    }
  }

  public static function verifyAclRulesOnCategory2($clientId, $token, $categoryId, $userName, $rules, $source = "USER") {
    try {
      //Thronintegration_Api::validateVerifyAclRulesOnCategory($clientId, $token, $categoryId, $userName, $rules);
      $param = [
        "rule" => [
          "items" => [
          ],
        ],
      ];

      foreach ($rules as $singleRule) {
        $param["rule"]["items"][] = [
          "targetObjId" => $categoryId,
          "targetObjClass" => "CATEGORY",
          "acl" => [
            "sourceObjId" => $userName,
            "sourceObjClass" => $source,
            "rules" => [
              [
                "rule" => $singleRule["ruleType"],
                "enabled" => TRUE,
                "applyToSpreadTargets" => $singleRule["ruleSpread"],
              ],
            ],
          ],
        ];
      };

      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "aclinverserules/verify/" . $clientId;
      // check the rules
      $validateRoleResp = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $param, ["X-TOKENID" => $token]);
      if (!$validateRoleResp) {
        // error while checking the rules
        throw new \Exception(sprintf("Could not verify the ACL rules on %s, category %s", $clientId, $categoryId));
      }

      return json_decode($validateRoleResp, TRUE);
    } catch (\Exception $ex) {
      // TODO notify this error
      return FALSE;
    }
  }

  public static function listAclRulesOnCategory($clientId, $token, $categoryId) {
    try {
      //Thronintegration_Api::validateVerifyAclRulesOnCategory($clientId, $token, $categoryId, $userName, $rules);
      $param = [
        "targetObjClass" => "CATEGORY",
      ];

      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "aclinverserules/list/" . $clientId . "/" . $categoryId;
      // check the rules
      $listAclResp = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $param, ["X-TOKENID" => $token]);
      if (!$listAclResp) {
        // error while checking the rules
        throw new \Exception(sprintf("Could not list the ACL rules on %s, category %s", $clientId, $categoryId));
      }

      return json_decode($listAclResp, TRUE);
    } catch (\Exception $ex) {
      //var_dump($ex);
      // TODO notify this error
      return FALSE;
    }
  }

  public static function verifyUsername($clientId, $token, $username) {
    try {
      $param = [
        "username" => $username,
      ];
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xsso") . "vusermanager/verifyUsername/" . $clientId;
      // check the rules
      $verifyUsernameResp = Thronintegration_HTTP::doHTTP("FORM_POST", $url, $param, ["X-TOKENID" => $token]);

      if (!$verifyUsernameResp) {
        // error while checking the rules
        throw new \Exception(sprintf("Could not verify username on %s, category %s", $clientId, $username));
      }

      return json_decode($verifyUsernameResp, TRUE);
    } catch (\Exception $ex) {
      // TODO notify this error
      return FALSE;
    }
  }

  public static function buildCategoryTree(&$result, $category, $level) {
    // add this category
    $resObj = [
      "id" => $category["id"],
      "name" => $category["locales"]["0"]["name"],
      "icon" => "categoryLevel-" . $level//,
      //"childrens"=>array()
    ];

    // take care of the children
    //var_dump($category);exit;
    if (isset($category["linkedCategories"]) && count($category["linkedCategories"]) > 0) {
      $resObj["childrens"] = [];
    }

    foreach ($category["linkedCategories"] as $child) {
      Thronintegration_Api::buildCategoryTree($resObj["childrens"], $child, ($level + 1));
    }


    array_push($result, $resObj);
  }

  private static function validateDetailClient($clientId, $token) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("invalid clientId");
    }

    if (!isset($token) || !Thronintegration_Utils::isValidToken($token)) {
      throw new \Exception("invalid token");
    }
  }

  public static function detailClient($clientId, $token) {
    try {
      Thronintegration_Api::validateDetailClient($clientId, $token);

      $param = ["clientId" => $clientId];
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "client/detailClient";
      // check the rules
      $detailClientResp = Thronintegration_HTTP::doHTTP("GET", $url, $param, ["X-TOKENID" => $token]);
      if (!$detailClientResp) {
        // error while checking the rules
        throw new \Exception(sprintf("Could not get the client details on %s", $clientId));
      }
      return json_decode($detailClientResp, TRUE);
    } catch (\Exception $ex) {
      // TODO notify this error
      return FALSE;
    }
  }

  /**
   * Returns the information about the tag by it's ID
   * @param $clientId
   * @param $token
   * @param $classificationId
   * @param $tagId
   *
   * @return array
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  public static function getITagDefinitionDetail($clientId, $token, $classificationId, $tagId, $show_linked = TRUE, $show_sub = FALSE) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $query = [];
      if ($show_linked) {
        $query['showLinkedMetadata'] = "true";
      }

      if ($show_sub) {
        $query['showSubNodeIds'] = "true";
      }

      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "itagdefinition/detail/$clientId/$classificationId/$tagId";
      if (!empty($query)) {
        $url .= '?' . http_build_query($query);
      }

      $detailTagResponse = Thronintegration_HTTP::doHTTP("GET", $url, FALSE, ["X-TOKENID" => $token]);
      if (!$detailTagResponse) {
        // error while updating app... TODO notify!
        throw new \Exception(sprintf("Could not get the tag detail for tag %s on %s", $tagId, $clientId));
      }
      $detailTagObj = json_decode($detailTagResponse, TRUE);
      if (!isset($detailTagObj["resultCode"]) || $detailTagObj["resultCode"] != "OK") {
        if (isset($detailTagObj["resultCode"]) && $detailTagObj["resultCode"] == "ITAG_NOT_FOUND") {
          $res["status"] = "ITAG_NOT_FOUND";
          return $res;
        }
        else {
          throw new \Exception(sprintf("Could not get the tag definition for tag %s on %s: invalid result", $tagId, $clientId));
        }
      }
      $res["status"] = "OK";
      $res["item"] = $detailTagObj["item"];
    }
    catch (AppTokenExpiredException $ex) {
      throw $ex;
    }
    catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function insertTag($clientId, $token, $classificationId, $tagObj) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "itagdefinition/insert/$clientId/$classificationId";
      $tagResponse = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $tagObj, ["X-TOKENID" => $token]);
      if (!$tagResponse) {
        // error while updating app... TODO notify!
        throw new \Exception(sprintf("Could not insert the tag to the classification %s on %s", $classificationId, $clientId));
      }
      $tagResponseObj = json_decode($tagResponse, TRUE);
      if (!isset($tagResponseObj["resultCode"]) || $tagResponseObj["resultCode"] != "OK") {
        throw new \Exception(sprintf("Could not insert the tag on %s: invalid result", $clientId));
      }
      $res["status"] = "OK";
      $res["item"] = $tagResponseObj["item"];
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function listIMetadataDefinition($clientId, $token, $classificationId, $search_string = NULL) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $ended = FALSE;
      $offset = 0;
      $meta = [];

      $query_params = [
        'offset' => $offset,
      ];
      if ($search_string) {
        $query_params['text'] = $search_string;
      }

      while (!$ended) {
        $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "imetadatadefinition/listGet/$clientId/$classificationId?" .build_query($query_params);

        $metaResponse = Thronintegration_HTTP::doHTTP("GET", $url, NULL, ["X-TOKENID" => $token]);
        if (!$metaResponse) {
          // error while updating app... TODO notify!
          throw new \Exception(sprintf("Could not get the metadata definition for the classification %s on %s", $classificationId, $clientId));
        }
        $metaResponseObj = json_decode($metaResponse, TRUE);
        if (!isset($metaResponseObj["resultCode"]) || $metaResponseObj["resultCode"] != "OK") {
          throw new \Exception(sprintf("Could not get the metadata on %s: invalid result", $clientId));
        }

        if (count($metaResponseObj["items"]) == 0) {
          $ended = TRUE;
        }
        else {
          foreach ($metaResponseObj["items"] as $item) {
            array_push($meta, $item);
          }

          $offset += count($metaResponseObj["items"]);
        }
      }

      $res["status"] = "OK";
      $res["items"] = $meta;
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function searchIMetadataDefinitionByKeys($clientId, $token, $classificationId, $keys = FALSE) {
    if (!$keys || (is_array($keys) && count($keys) == 0)) {
      return Thronintegration_Api::listIMetadataDefinition($clientId, $token, $classificationId);
    }

    $res = ["status" => "ERROR", "errorDescription" => ""];
    if ($keys && !is_array($keys)) {
      throw new \Exception("Invalid keys filter");
    }

    try {
      $ended = FALSE;
      $offset = 0;
      $meta = [];

      $params = [
        "criteria" => [
          "keys" => $keys,
        ],
        "limit" => 50,
      ];
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "imetadatadefinition/list/$clientId/$classificationId";

      while (!$ended) {
        $params["offset"] = $offset;
        $metaResponse = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $token]);
        if (!$metaResponse) {
          // error while updating app... TODO notify!
          throw new \Exception(sprintf("Could not get the metadata definition for the classification %s on %s", $classificationId, $clientId));
        }
        $metaResponseObj = json_decode($metaResponse, TRUE);
        if (!isset($metaResponseObj["resultCode"]) || $metaResponseObj["resultCode"] != "OK") {
          throw new \Exception(sprintf("Could not get the metadata definition on %s: invalid result", $clientId));
        }

        if (count($metaResponseObj["items"]) == 0) {
          $ended = TRUE;
        }
        else {
          foreach ($metaResponseObj["items"] as $item) {
            array_push($meta, $item);
          }

          $offset += count($metaResponseObj["items"]);
        }
      }

      $res["status"] = "OK";
      $res["items"] = $meta;
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function insertIMetadataDefinition($clientId, $token, $classificationId, $metaObj) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "imetadatadefinition/insert/$clientId/$classificationId";
      $metaResponse = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $metaObj, ["X-TOKENID" => $token]);
      if (!$metaResponse) {
        // error while updating app... TODO notify!
        throw new \Exception(sprintf("Could not insert the metadata to the classification %s on %s", $classificationId, $clientId));
      }
      $metaResponseObj = json_decode($metaResponse, TRUE);
      //var_dump($metaResponseObj);
      if (!isset($metaResponseObj["resultCode"]) || $metaResponseObj["resultCode"] != "OK") {
        throw new \Exception(sprintf("Could not insert the metadata on %s: invalid result", $clientId));
      }
      $res["status"] = "OK";
      $res["item"] = $metaResponseObj["item"];
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function linkImetadataToTag($clientId, $token, $classificationId, $tagId, $metaId) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "imetadatadefinition/linkITag/$clientId/$classificationId/$tagId/$metaId";
      $tagObj = ["pos" => 0];
      $metaResponse = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $tagObj, ["X-TOKENID" => $token]);
      if (!$metaResponse) {
        // error while updating app... TODO notify!
        throw new \Exception(sprintf("Could not attach the metadata %s to the tag %s for the classification %s on %s", $metaId, $tagId, $classificationId, $clientId));
      }
      $metaResponseObj = json_decode($metaResponse, TRUE);
      if (!isset($metaResponseObj["resultCode"]) || $metaResponseObj["resultCode"] != "OK") {
        throw new \Exception(sprintf("Could not attach the metadata %s to the tag %s for the classification %s on %s", $metaId, $tagId, $classificationId, $clientId));
      }
      $res["status"] = "OK";
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function detailMetadata($clientId, $token, $classificationId, $idOrKey) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "imetadatadefinition/detail/$clientId/$classificationId/$idOrKey";
      $metaResponse = Thronintegration_HTTP::doHTTP("GET", $url, FALSE, ["X-TOKENID" => $token]);
      if (!$metaResponse) {
        // error while updating app... TODO notify!
        throw new \Exception(sprintf("Could not insert the metadata to the classification %s on %s", $classificationId, $clientId));
      }

      $metaResponseObj = json_decode($metaResponse, TRUE);
      if (isset($metaResponseObj["resultCode"]) && $metaResponseObj["resultCode"] == "METADATA_NOT_FOUND") {
        $res["status"] = "METADATA_NOT_FOUND";
        return $res;
      }
      else {
        if (!isset($metaResponseObj["resultCode"]) || $metaResponseObj["resultCode"] != "OK") {
          throw new \Exception(sprintf("Could not get the metadata detail for %s on classification %s on %s: invalid result", $idOrKey, $classificationId, $clientId));
        }
      }
      $res["status"] = "OK";
      $res["item"] = $metaResponseObj["item"];
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function updateIMetadata($clientId, $token, $contentId, $classificationId, $metaName, $metaValue, $lang = FALSE) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "imetadata/update/$clientId/$classificationId";

      $params = [
        "imetadata" => [
          "key" => $metaName,
          "value" => $metaValue,
        ],
        "target" => [
          "id" => $contentId,
          "entityType" => "CONTENT",
        ],
      ];

      if ($lang) {
        $params["imetadata"]["lang"] = $lang;
      }

      $updateMetaResponse = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $token]);

      if (!$updateMetaResponse) {
        // error while updating app... TODO notify!
        throw new \Exception(sprintf("Could not remove the tag from the content %s on %s", $contentId, $clientId));
      }
      $updateMetaObj = json_decode($updateMetaResponse, TRUE);
      if (!isset($updateMetaObj["resultCode"]) || $updateMetaObj["resultCode"] != "OK") {
        throw new \Exception(sprintf("Could not update the metadata on content %s, metadata %s on %s: invalid result", $contentId, $metaName, $clientId));
      }
      $res["status"] = "OK";
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function insertIMetadata($clientId, $token, $contentId, $classificationId, $metaName, $metaValue, $lang = FALSE) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "imetadata/insert/$clientId/$classificationId";

      $params = [
        "imetadata" => [
          "key" => $metaName,
          "value" => $metaValue,
        ],
        "target" => [
          "id" => $contentId,
          "entityType" => "CONTENT",
        ],
      ];

      if ($lang) {
        $params["imetadata"]["lang"] = $lang;
      }

      $updateMetaResponse = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $token]);

      if (!$updateMetaResponse) {
        // error while updating app... TODO notify!
        throw new \Exception(sprintf("Could not insert the metadata %s on the content %s on %s", $metaName, $contentId, $clientId));
      }

      $updateMetaObj = json_decode($updateMetaResponse, TRUE);

      if (!isset($updateMetaObj["resultCode"]) || $updateMetaObj["resultCode"] != "OK") {
        if (isset($updateMetaObj["resultCode"]) && $updateMetaObj["resultCode"] == "KEY_ALREADY_DEFINED") {
          // update the metadata
          $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "imetadata/update/$clientId/$classificationId";
          $updateMetaResponse = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $token]);

          if (!$updateMetaResponse) {
            // error while updating app... TODO notify!
            throw new \Exception(sprintf("Could not insert the metadata %s on the content %s on %s", $metaName, $contentId, $clientId));
          }

          $updateMetaObj = json_decode($updateMetaResponse, TRUE);

          if (!isset($updateMetaObj["resultCode"]) || $updateMetaObj["resultCode"] != "OK") {
            throw new \Exception(sprintf("Could not update the metadata on content %s, metadata %s on %s: invalid result", $contentId, $metaName, $clientId));
          }
        }
        else {
          throw new \Exception(sprintf("Could not insert the metadata on content %s, metadata %s on %s: invalid result", $contentId, $metaName, $clientId));
        }
      }

      $res["status"] = "OK";
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function removeIMetadata($clientId, $token, $contentId, $classificationId, $metaName) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "imetadata/remove/$clientId/$classificationId/$metaName";

      $params = [
        "target" => [
          "id" => $contentId,
          "entityType" => "CONTENT",
        ],
      ];

      $updateMetaResponse = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $token]);

      if (!$updateMetaResponse) {
        // error while updating app... TODO notify!
        throw new \Exception(sprintf("Could not remove the tag from the content %s on %s", $contentId, $clientId));
      }
      $updateMetaObj = json_decode($updateMetaResponse, TRUE);
      if (!isset($updateMetaObj["resultCode"]) || $updateMetaObj["resultCode"] != "OK") {
        if (isset($updateMetaObj["resultCode"]) && $updateMetaObj["resultCode"] == "METADATA_NOT_FOUND") {
          $res["status"] = "METADATA_NOT_FOUND";
          return $res;
        }
        else {
          throw new \Exception(sprintf("Could not remove the metadata on content %s, metadata %s on %s: invalid result", $contentId, $metaName, $clientId));
        }
      }

      $res["status"] = "OK";
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  private static function validateTagDefinitionList($clientId, $token, $classificationId) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($token) || !Thronintegration_Utils::IsValidToken($token)) {
      throw new \Exception("Invalid token id!");
    }
    if (!isset($classificationId) || Thronintegration_Utils::IsNullOrEmptyString($classificationId)) {
      throw new \Exception("Invalid classification id!");
    }
  }

  /**
   * Returns the tags by classification
   * @param $clientId
   * @param $token
   * @param $classificationId
   * @param bool $filterOnIds
   * @param bool $returnNames
   * @param bool $showSubNodeIds
   * @param NULL $depth
   * @param NULL $search_text
   * @param string $lang
   *
   * @return array
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  public static function tagDefinitionList($clientId, $token, $classificationId, $filterOnIds = FALSE, $returnNames = FALSE, $showSubNodeIds = FALSE, $depth = NULL, $search_text = NULL, $lang = 'EN')  {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      Thronintegration_Api::validateTagDefinitionList($clientId, $token, $classificationId);
      $url = Thronintegration_Api::getThronEndpoint($clientId, 'xintelligence') . "itagdefinition/list/$clientId/$classificationId";
      $offset = 0;
      $ended = FALSE;
      $criteria = [];
      $params = [
        'showLinkedMetadata' => FALSE,
        'showSubNodeIds' => $showSubNodeIds,
        'orderBy' => 'createdDate_D',
        'returnTotalResults' => TRUE,
        'limit' => 50,
      ];

      if ($filterOnIds && is_array($filterOnIds) && count($filterOnIds) > 0) {
        $criteria['ids'] = $filterOnIds;
      }

      if (!Thronintegration_Utils::IsNullOrEmptyString($search_text)) {
        $criteria['text'] = $search_text;
        $criteria['textSearchOptions'] = [
          'searchKeyOption' => 'EXACT_MATCH',
          'searchOnFields' => [ 'LABEL' ],
        ];
        $criteria['lang'] = strtoupper($lang);
      }

      if ($depth !== NULL) {
        $criteria['excludeLevelHigherThan'] = intval($depth);
      }

      if (!empty($criteria)) {
        $params['criteria'] = $criteria;
      }

      $tags = [];
      while (!$ended) {
        $params['offset'] = $offset;
        $tagResp = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $token]);
        if (!$tagResp || Thronintegration_Utils::IsNullOrEmptyString($tagResp)) {
          $ended = TRUE;
        }
        else {
          $tagRespObj = json_decode($tagResp, TRUE);
          if (!$tagRespObj || !isset($tagRespObj["resultCode"]) || $tagRespObj["resultCode"] != "OK") {
            if (isset($tagRespObj["resultCode"]) && $tagRespObj["resultCode"] == "CLASSIFICATION_NOT_FOUND") {
              throw new \Exception("CLASSIFICATION_NOT_FOUND");
            }
            $ended = TRUE;
          }
          else {
            //var_dump($tagRespObj["items"]);
            if (count($tagRespObj["items"]) == 0) {
              $ended = TRUE;
            }
            else {
              $offset += count($tagRespObj["items"]);
              foreach ($tagRespObj["items"] as $item) {
                if ($returnNames) {
                  if (isset($item["prettyId"]) && !Thronintegration_Utils::IsNullOrEmptyString($item["prettyId"])) {
                    $tags[$item["id"]] = [
                      "prettyId" => $item["prettyId"],
                      "names" => $item["names"],
                    ];
                  }
                  else {
                    $tags[$item["id"]] = ["names" => $item["names"]];
                  }
                }
                else {
                  if (isset($item["prettyId"]) && !Thronintegration_Utils::IsNullOrEmptyString($item["prettyId"])) {
                    $tags[$item["prettyId"]] = $item["id"];
                  }
                  else {
                    $tags[$item["id"]] = $item["id"];
                  }
                }

                if ($showSubNodeIds && isset($item['subNodeIds'])) {
                  if (!is_array($tags[$item['id']])) {
                    $tags[$item['id']] = [
                      'id' => $item['id'],
                    ];
                  }
                  $tags[$item['id']]['subNodeIds'] = $item['subNodeIds'];
                }
              }
            }
          }
        }
      }

      $res["status"] = "OK";
      $res["tags"] = $tags;

    }
    catch (AppTokenExpiredException $ex) {
      throw $ex;
    }
    catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  private static function validateClassificationDetail($clientId, $token, $classificationId) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($token) || !Thronintegration_Utils::IsValidToken($token)) {
      throw new \Exception("Invalid token id!");
    }
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($classificationId)) {
      throw new \Exception("Invalid classificationId!");
    }
  }

  public static function classificationDetail($clientId, $token, $classificationId) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      Thronintegration_Api::validateClassificationDetail($clientId, $token, $classificationId);

      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "classification/detail/$clientId/$classificationId";
      $detResp = Thronintegration_HTTP::doHTTP("GET", $url, FALSE, ["X-TOKENID" => $token]);
      $detObj = json_decode($detResp, TRUE);

      return $detObj;
    } catch (\Exception $ex) {
      $res["resultCode"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
      return $res;
    }
  }

  private static function validateClassificationList($clientId, $token) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($token) || !Thronintegration_Utils::IsValidToken($token)) {
      throw new \Exception("Invalid token id!");
    }
  }

  /**
   * Returns the Classification list
   * @param $clientId
   * @param $token
   * @param bool $onlyActive
   * @param bool $onlyTheOnesTheUserCanManage
   *
   * @return array
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  public static function classificationList($clientId, $token, $onlyActive = TRUE, $onlyTheOnesTheUserCanManage = FALSE) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      Thronintegration_Api::validateClassificationList($clientId, $token);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "classification/list/$clientId";
      $offset = 0;
      $ended = FALSE;
      $params = [
        "limit" => 50,
      ];
      if ($onlyActive) {
        $params["criteria"] = ["active" => TRUE];
      }

      $classifications = [];
      while (!$ended) {
        $params["offset"] = $offset;
        $tagResp = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $token]);

        if (!$tagResp || Thronintegration_Utils::IsNullOrEmptyString($tagResp)) {
          $ended = TRUE;
        }
        else {
          $tagRespObj = json_decode($tagResp, TRUE);
          if (!$tagRespObj || !isset($tagRespObj["resultCode"]) || $tagRespObj["resultCode"] != "OK") {
            throw new \Exception("Invalid response from classification/list for client $clientId");
          }
          else {
            if (count($tagRespObj["items"]) == 0) {
              $ended = TRUE;
            }
            else {
              $offset += count($tagRespObj["items"]);
              foreach ($tagRespObj["items"] as $item) {
                array_push($classifications, $item);
              }
            }
          }
        }
      }

      if ($onlyTheOnesTheUserCanManage) {
        // check this token: permissions/insert can be invoked only by users
        // with role THRON_CLASSIFICATIONS_MANAGER or THRON_CLASS_[CLASSID]_MANAGER and CORE_MANAGE_USERS
        $roleValidObj = Thronintegration_Api::validateUserRole($clientId, $token, "THRON_CLASSIFICATIONS_MANAGER");
        if ($roleValidObj && isset($roleValidObj["status"]) && $roleValidObj["status"] == "OK") {
          // this user is a classifications manager, and thus we can safely return all the classifications
          $res["classifications"] = $classifications;
        }
        else {
          // this user is not a classifications manager; has he the role CORE_MANAGE_USERS?
          $roleValidObj = Thronintegration_Api::validateUserRole($clientId, $token, "CORE_MANAGE_USERS");
          if ($roleValidObj && isset($roleValidObj["status"]) && $roleValidObj["status"] == "OK") {
            // chech the classifications the user is manager for:
            $classificationsRet = [];
            foreach ($classifications as $cl) {
              $roleValidObj = Thronintegration_Api::validateUserRole($clientId, $token, "THRON_CLASS_{$cl["id"]}_MANAGER");
              if ($roleValidObj && isset($roleValidObj["status"]) && $roleValidObj["status"] == "OK") {
                // this user is a classifications manager, and thus we can safely return all the classifications
                array_push($classificationsRet, $cl);
              }
            }

            $res["classifications"] = $classificationsRet;
          }
          else {
            // this user is neither a classifications manager nor he can manage users; the classifications list will be empty.
            $res["classifications"] = [];
          }
        }
      }
      else {
        $res["classifications"] = $classifications;
      }

      $res["status"] = "OK";
    }
    catch (AppTokenExpiredException $ex) {
      throw $ex;
    }
    catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  private static function validateGetClassificationPermissions($clientId, $token, $classificationId) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($token) || !Thronintegration_Utils::IsValidToken($token)) {
      throw new \Exception("Invalid token id!");
    }
    if (!isset($classificationId) || Thronintegration_Utils::IsNullOrEmptyString($classificationId)) {
      throw new \Exception("Invalid classification id!");
    }
  }

  public static function getClassificationPermissions($clientId, $token, $classificationId) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      Thronintegration_Api::validateGetClassificationPermissions($clientId, $token, $classificationId);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "permissions/list/$clientId/$classificationId";
      $offset = 0;
      $ended = FALSE;
      $params = [
        "limit" => 50,
      ];

      $items = [];

      while (!$ended) {
        $params["offset"] = $offset;

        $getClassificationResp = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $token]);

        if (!$getClassificationResp) {
          // error while getting the context list... TODO notify!
          throw new \Exception("Could not get the permissions for classification $classificationId on clientId $clientId");
        }

        $getClassificationRespObj = FALSE;
        try {
          $getClassificationRespObj = json_decode($getClassificationResp, TRUE);
        } catch (\Exception $e) {
          throw new \Exception("Could not get permissions for classification $classificationId on clientId $clientId: invalid response from server");
        }

        if (!$getClassificationRespObj || !isset($getClassificationRespObj["resultCode"]) || !isset($getClassificationRespObj["resultCode"]) || $getClassificationRespObj["resultCode"] != "OK") {
          throw new \Exception("Could not get the permissions for classification $classificationId on clientId $clientId: resultCode is not OK");
        }

        if (count($getClassificationRespObj["items"]) == 0) {
          $ended = TRUE;
        }
        else {
          foreach ($getClassificationRespObj["items"] as $item) {
            array_push($items, $item);
          }
          $offset += count($getClassificationRespObj["items"]);
        }
      }

      $res["status"] = "OK";
      $res["items"] = $items;
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  private static function validateContentDetail($clientId, $token, $contentId) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($token) || !Thronintegration_Utils::IsValidToken($token)) {
      throw new \Exception("Invalid token id!");
    }
    if (!isset($contentId) || !Thronintegration_Utils::IsValidToken($contentId)) {
      throw new \Exception("Invalid content id!");
    }
  }

  public static function contentDetail($clientId, $token, $contentId, $divArea=FALSE) {
    $res = new \stdClass();
    $res->resultCode = "ERROR";
    $res->errorDescription = "";
    $res->content = NULL;

    try {
      Thronintegration_Api::validateContentDetail($clientId, $token, $contentId);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "delivery/getContentDetail?clientId=$clientId&xcontentId=$contentId";
      if($divArea && trim($divArea) != "")
        $url .= "&divArea=$divArea";

      $contentDetailResp = Thronintegration_HTTP::doHTTP("GET", $url, FALSE, ["X-TOKENID" => $token]);
      if ($contentDetailResp && !Thronintegration_Utils::IsNullOrEmptyString($contentDetailResp)) {
        $contentDetailObj = json_decode($contentDetailResp, false);
        if (!$contentDetailObj || !isset($contentDetailObj->resultCode) || $contentDetailObj->resultCode != "OK") {
          throw new \Exception("Invalid response while getting content detail for client $clientId, content $contentId");
        }

        $res->content = $contentDetailObj->content;
        $res->resultCode = "OK";
      }
      else {
        throw new \Exception("Invalid response while getting content detail for client $clientId, content $contentId");
      }
    } catch (\Exception $ex) {
      $res->resultCode = "ERROR";
      $res->errorDescription = $ex->getMessage();
    }
    return $res;
  }

  private static function validateReplaceThumbnailInPublishedContent($clientId, $tokenId, $xcontentId, $thumbFileName, $thumbSourceArr) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($tokenId) || !Thronintegration_Utils::IsValidToken($tokenId)) {
      throw new \Exception("Invalid token id!");
    }
    if (!isset($xcontentId) || !Thronintegration_Utils::IsValidToken($xcontentId)) {
      throw new \Exception("Invalid content id!");
    }
    if (!isset($thumbFileName) || Thronintegration_Utils::IsNullOrEmptyString($thumbFileName)) {
      throw new \Exception("Invalid thumbnail file name!");
    }
    if (!isset($thumbSourceArr) || Thronintegration_Utils::IsNullOrEmptyString($thumbSourceArr)) {
      throw new \Exception("Invalid thumbnail source byte array string!");
    }
  }

  public static function replaceThumbnailInPublishedContent($clientId, $tokenId, $xcontentId, $thumbFileName, $thumbSourceArr) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      Thronintegration_Api::validateReplaceThumbnailInPublishedContent($clientId, $tokenId, $xcontentId, $thumbFileName, $thumbSourceArr);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xadmin") . "publishingprocess/replaceThumbnailInPublishedContent";
      $params = new \stdClass();
      $params->clientId = $clientId;
      $params->params = new \stdClass();
      $params->params->thumbSource = new \stdClass();
      $params->params->thumbSource->fileName = $thumbFileName;
      $params->params->thumbSource->source = $thumbSourceArr;
      $params->params->xcontentId = $xcontentId;

      $replaceResp = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $tokenId]);
      if ($replaceResp && !Thronintegration_Utils::IsNullOrEmptyString($replaceResp)) {
        $replaceRespObj = json_decode($replaceResp, TRUE);
        if (!$replaceRespObj || !isset($replaceRespObj["resultCode"]) || $replaceRespObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response while changing thumbnail for client $clientId, content $xcontentId");
        }

        $res["status"] = "OK";
      }
      else {
        throw new \Exception("Invalid response while changing thumbnail for $clientId, content $xcontentId");
      }
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  private static function validateGetPlayerTemplates($clientId, $tokenId) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($tokenId) || !Thronintegration_Utils::IsValidToken($tokenId)) {
      throw new \Exception("Invalid token id!");
    }
  }

  public static function getPlayerPlatformTemplates($clientId, $tokenId) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      Thronintegration_Api::validateGetPlayerTemplates($clientId, $tokenId);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xsso") . "platforminfo/listTemplates";

      $params = [
        "criteria" => (object) [],
        "options" => (object) [],
      ];

      $getPlayerTemplates = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $tokenId]);

      if ($getPlayerTemplates && !Thronintegration_Utils::IsNullOrEmptyString($getPlayerTemplates)) {
        $getPlayerTemplatesObj = json_decode($getPlayerTemplates, TRUE);
        if (!$getPlayerTemplatesObj || !isset($getPlayerTemplatesObj["resultCode"]) || $getPlayerTemplatesObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response while listing player templates for client $clientId");
        }

        $res = json_decode($getPlayerTemplates);
      }
      else {
        throw new \Exception("Invalid response while listing player templates for client $clientId");
      }
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  private static function validateGetPlayerCustomTemplates($clientId, $tokenId) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($tokenId) || !Thronintegration_Utils::IsValidToken($tokenId)) {
      throw new \Exception("Invalid token id!");
    }
  }

  /**
   * Returns the available templates for the THRON player
   * @param $clientId
   * @param $tokenId
   * @param $offset
   *
   * @return array|mixed
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  public static function getPlayerCustomTemplates($clientId, $tokenId, $offset) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      Thronintegration_Api::validateGetPlayerCustomTemplates($clientId, $tokenId);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "playerembedtemplate/list/$clientId";

      $params = [
        "criteria" => (object) [],
        "options" => (object) [],
        "offset" => $offset,
      ];

      $getPlayerCustomTemplates = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $tokenId]);

      if ($getPlayerCustomTemplates && !Thronintegration_Utils::IsNullOrEmptyString($getPlayerCustomTemplates)) {
        $getPlayerCustomTemplatesObj = json_decode($getPlayerCustomTemplates, TRUE);
        if (!$getPlayerCustomTemplatesObj || !isset($getPlayerCustomTemplatesObj["resultCode"]) || $getPlayerCustomTemplatesObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response while listing player custom templates for client $clientId");
        }

        $res = json_decode($getPlayerCustomTemplates, TRUE);
      }
      else {
        throw new \Exception("Invalid response while listing player custom templates for client $clientId");
      }
    }
    catch (AppTokenExpiredException $ex) {
      throw $ex;
    }
    catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  /**
   * Returns the embed player template data
   * @param $clientId
   * @param $tokenId
   * @param $templateId
   *
   * @return array|mixed
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  public static function getPlayerCustomTemplateData($clientId, $tokenId, $templateId) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "playerembedtemplate/detail/$clientId/$templateId";
      $params = [];

      $jsonTemplateData = Thronintegration_HTTP::doHTTP("GET", $url, $params, ["X-TOKENID" => $tokenId]);

      if (!Thronintegration_Utils::IsNullOrEmptyString($jsonTemplateData)) {
        $templateData = json_decode($jsonTemplateData, TRUE);
        if (!$templateData || !isset($templateData["resultCode"]) || $templateData["resultCode"] != "OK") {
          throw new \Exception("Invalid response while listing embed player templates for client $clientId");
        }

        $res = $templateData;
      }
      else {
        throw new \Exception("Invalid response while listing embed player templates for client $clientId");
      }
    }
    catch (AppTokenExpiredException $ex) {
      throw $ex;
    }
    catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  private static function validateGetContextList($clientId, $tokenId) {
    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($tokenId) || !Thronintegration_Utils::IsValidToken($tokenId)) {
      throw new \Exception("Invalid token id!");
    }
  }

  public static function getContextList($clientId, $tokenId) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      Thronintegration_Api::validateGetContextList($clientId, $tokenId);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "context/list/$clientId";

      $params = [
        "clientId" => $clientId,
        "criteria" => (object) [],

      ];

      $getContextList = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $tokenId]);
      //var_dump($getContextList);
      if ($getContextList && !Thronintegration_Utils::IsNullOrEmptyString($getContextList)) {
        $getContextListObj = json_decode($getContextList, TRUE);
        if (!$getContextListObj || !isset($getContextListObj["resultCode"]) || $getContextListObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response while listing player custom templates for client $clientId");
        }

        $res = json_decode($getContextList);
      }
      else {
        throw new \Exception("Invalid response while listing player custom templates for client $clientId");
      }
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  private static function validateInsertContextList($clientId, $tokenId, $contextName, $lang) {

    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($tokenId) || !Thronintegration_Utils::IsValidToken($tokenId)) {
      throw new \Exception("Invalid token id!");
    }
    if (!isset($contextName) || Thronintegration_Utils::IsNullOrEmptyString($contextName)) {
      throw new \Exception("Invalid contextName!");
    }
    if (!isset($lang) || Thronintegration_Utils::IsNullOrEmptyString($lang)) {
      throw new \Exception("Invalid lang!");
    }
  }

  public static function insertContext($clientId, $tokenId, $contextName, $lang) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      Thronintegration_Api::validateInsertContextList($clientId, $tokenId, $contextName, $lang);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xintelligence") . "context/insert/$clientId";

      $params = [
        "clientId" => $clientId,
        "value" => [
          "names" => [
            [
              "lang" => $lang,
              "label" => $contextName,
            ],
          ],
        ],
      ];

      $insertContext = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $tokenId]);
      if ($insertContext && !Thronintegration_Utils::IsNullOrEmptyString($insertContext)) {
        $insertContextObj = json_decode($insertContext, TRUE);
        if (!$insertContextObj || !isset($insertContextObj["resultCode"]) || $insertContextObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response while listing player custom templates for client $clientId");
        }

        $res = $insertContextObj;
      }
      else {
        throw new \Exception("Invalid response while listing player custom templates for client $clientId");
      }
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  private static function validateInsertEmbedcode($clientId, $tokenId, $contextName, $lang) {

    if (!isset($clientId) || Thronintegration_Utils::IsNullOrEmptyString($clientId)) {
      throw new \Exception("Invalid clientId!");
    }
    if (!isset($tokenId) || !Thronintegration_Utils::IsValidToken($tokenId)) {
      throw new \Exception("Invalid token id!");
    }
    if (!isset($contextName) || Thronintegration_Utils::IsNullOrEmptyString($contextName)) {
      throw new \Exception("Invalid contextName!");
    }
    if (!isset($lang) || Thronintegration_Utils::IsNullOrEmptyString($lang)) {
      throw new \Exception("Invalid lang!");
    }
  }

  /**
   * Comands the API to insert the player embed code on external page with given
   * credetials and options
   * @param $clientId
   * @param $tokenId
   * @param $embedName
   * @param $source
   * @param $contextId
   * @param $templateId
   * @param $templateType
   * @param $values
   * @param $skipPkeyCreation
   *
   * @return array|mixed
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  public static function insertEmbedCode($clientId, $tokenId, $embedName, $source, $contextId, $templateId, $templateType, $values, $skipPkeyCreation) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      //Thronintegration_Api::validateInsertContextList($clientId, $tokenId,$contextName,$lang);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "playerembedcode/insert/$clientId";
      //echo $url;
      $params = [
        "clientId" => $clientId,
        "source" => [
          "id" => $source['id'],
          "entityType" => $source['type'],
        ],
        "value" => [
          "name" => $embedName,
          "template" => [
            "templateId" => $templateId,
            "templateType" => $templateType,
          ],
          "embedTarget" => "GENERIC",
          "enabled" => TRUE,
          "values" => $values,
        ],
        "skipPkeyCreation" => $skipPkeyCreation,
      ];

      if($contextId)
        $params["value"]["useContextId"] = $contextId;

      $insertEmbedCode = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $tokenId]);
      if ($insertEmbedCode && !Thronintegration_Utils::IsNullOrEmptyString($insertEmbedCode)) {
        $insertEmbedCodeObj = json_decode($insertEmbedCode, TRUE);
        if (!$insertEmbedCodeObj || !isset($insertEmbedCodeObj["resultCode"]) || $insertEmbedCodeObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response while inserting embed code for client $clientId");
        }

        $res = $insertEmbedCodeObj;
      }
      else {
        throw new \Exception("Invalid response while inserting embed code for client $clientId");
      }
    }
    catch (AppTokenExpiredException $ex) {
      throw $ex;
    }
    catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function removeEmbedCode($clientId, $tokenId, $embedCodeId, $categoryId) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      //Thronintegration_Api::validateInsertContextList($clientId, $tokenId,$contextName,$lang);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "playerembedcode/remove/$clientId";
      //echo $url;
      $params = [
        "clientId" => $clientId,
        "embedCodeId" => $embedCodeId,
        "source" => [
          "id" => $categoryId,
          "entityType" => "CATEGORY",
        ],
      ];
      //echo json_encode($params);exit;
      $removeEmbedCode = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $tokenId]);
      //var_dump($removeEmbedCode);exit;
      if ($removeEmbedCode && !Thronintegration_Utils::IsNullOrEmptyString($removeEmbedCode)) {
        $removeEmbedCodeObj = json_decode($removeEmbedCode, TRUE);
        if (!$removeEmbedCodeObj || !isset($removeEmbedCodeObj["resultCode"]) || $removeEmbedCodeObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response while inserting embed code for client $clientId");
        }

        $res = $removeEmbedCodeObj;
      }
      else {
        throw new \Exception("Invalid response while inserting embed code for client $clientId");
      }
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function updateEmbedCode($clientId, $tokenId, $embedCodeId, $embedName, $categoryId, $contextId, $templateId, $templateType) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      //Thronintegration_Api::validateInsertContextList($clientId, $tokenId,$contextName,$lang);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "playerembedcode/update/$clientId";
      //echo $url;
      $params = [
        "embedCodeId" => $embedCodeId,
        "source" => [
          "id" => $categoryId,
          "entityType" => "CATEGORY",
        ],
        "update" => [
          "name" => $embedName,
          "template" => [
            "templateId" => $templateId,
            "templateType" => $templateType,
          ],
          "embedTarget" => "GENERIC",
          "useContextId" => $contextId,
        ],
      ];
      //echo json_encode($params);
      $updateEmbedCode = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $tokenId]);
      //var_dump(json_encode($updateEmbedCode));exit;
      if ($updateEmbedCode && !Thronintegration_Utils::IsNullOrEmptyString($updateEmbedCode)) {
        $updateEmbedCodeObj = json_decode($updateEmbedCode, TRUE);
        if (!$updateEmbedCodeObj || !isset($updateEmbedCodeObj["resultCode"]) || $updateEmbedCodeObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response while updating embed code for client $clientId");
        }

        $res = $updateEmbedCodeObj;
      }
      else {
        throw new \Exception("Invalid response while updating embed code for client $clientId");
      }
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function toggleEmbedCode($clientId, $tokenId, $embedCodeId, $embedStatus, $categoryId) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      //Thronintegration_Api::validateInsertContextList($clientId, $tokenId,$contextName,$lang);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "playerembedcode/update/$clientId";
      //echo $url;
      $params = [
        "embedCodeId" => $embedCodeId,
        "source" => [
          "id" => $categoryId,
          "entityType" => "CATEGORY",
        ],
        "update" => [
          "enabled" => $embedStatus,
        ],
      ];
      //echo json_encode($params);
      $toggleEmbedCode = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $tokenId]);
      //echo json_encode($toggleEmbedCode);exit;
      if ($toggleEmbedCode && !Thronintegration_Utils::IsNullOrEmptyString($toggleEmbedCode)) {
        $toggleEmbedCodeObj = json_decode($toggleEmbedCode, TRUE);
        if (!$toggleEmbedCodeObj || !isset($toggleEmbedCodeObj["resultCode"]) || $toggleEmbedCodeObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response while updating embed code for client $clientId");
        }

        $res = $toggleEmbedCodeObj;
      }
      else {
        throw new \Exception("Invalid response while updating embed code for client $clientId");
      }
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  public static function listEmbedCodes($clientId, $tokenId, $categoryId, $nextPage) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      //Thronintegration_Api::validateInsertContextList($clientId, $tokenId,$contextName,$lang);
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "playerembedcode/list/$clientId";
      //echo $url;
      $params = [
        "source" => [
          "id" => $categoryId,
          "entityType" => "CATEGORY",
        ],
        "criteria" => (object) [],
      ];
      if ($nextPage != "") {
        $params["nextPage"] = $nextPage;
      }

      $listEmbedCodes = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $params, ["X-TOKENID" => $tokenId]);

      if ($listEmbedCodes && !Thronintegration_Utils::IsNullOrEmptyString($listEmbedCodes)) {
        $listEmbedCodesObj = json_decode($listEmbedCodes, TRUE);
        if (!$listEmbedCodesObj || !isset($listEmbedCodesObj["resultCode"]) || $listEmbedCodesObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response while listing embed codes for client $clientId");
        }

        $res = $listEmbedCodesObj;
      }
      else {
        throw new \Exception("Invalid response while listing embed codes for client $clientId");
      }
    } catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  /**
   * Returns the media content detailed information.
   *
   * @param $clientId
   * @param $tokenId
   *
   * @return array|mixed
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  public static function getMediaContentDetails($clientId, $tokenId, $contentId) {
    $res = ["status" => "ERROR", "errorDescription" => ""];

    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xadmin") . "mediacontent/detailMediaContent";
      $params = new \stdClass();
      $params->clientId = $clientId;
      $params->xcontentId = $contentId;

      $resp = Thronintegration_HTTP::doHTTP("GET", $url, $params, ["X-TOKENID" => $tokenId]);
      if ($resp && !Thronintegration_Utils::IsNullOrEmptyString($resp)) {
        $respObj = json_decode($resp, TRUE);
        if (!$respObj || !isset($respObj["resultCode"]) || $respObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response getMediaContentDetails for client $clientId, content $contentId");
        }

        $res = $respObj;
        $res["status"] = "OK";
      }
      else {
        throw new \Exception("Invalid response while getMediaContentDetails for $clientId, content $contentId");
      }
    }
    catch (AppTokenExpiredException $ex) {
      throw $ex;
    }
    catch (\Exception $ex) {
      $res["status"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

  /**
   * Performs a content search
   *
   * @param $clientId
   * @param $tokenId
   * @param $search_param
   *
   * @return array|mixed
   * @throws \Drupal\thron\Exception\AppTokenExpiredException
   */
  public static function contentSearchLite($clientId, $tokenId, $search_param) {
    $res = ["resultCode" => "ERROR", "errorDescription" => ""];

    try {
      $url = Thronintegration_Api::getThronEndpoint($clientId, "xcontents") . "content/search/$clientId";

      $resp = Thronintegration_HTTP::doHTTP("JSON_POST", $url, $search_param, ["X-TOKENID" => $tokenId]);
      if ($resp && !Thronintegration_Utils::IsNullOrEmptyString($resp)) {
        $respObj = json_decode($resp, TRUE);
        if (!$respObj || !isset($respObj["resultCode"]) || $respObj["resultCode"] != "OK") {
          throw new \Exception("Invalid response contentSearch for client $clientId");
        }

        $res = $respObj;
        $res["resultCode"] = "OK";
      }
      else {
        throw new \Exception("Invalid response for contentSearch for $clientId");
      }
    }
    catch (AppTokenExpiredException $ex) {
      throw $ex;
    }
    catch (\Exception $ex) {
      $res["resultCode"] = "ERROR";
      $res["errorDescription"] = $ex->getMessage();
    }
    return $res;
  }

}