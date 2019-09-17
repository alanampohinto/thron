<?php

namespace Drupal\thron;

/**
 * Provides Drupal 8 THRON API.
 *
 * @package Drupal\thron
 */
interface THRONApiInterface {

  /**
   * @param array $configArgs
   *
   * @throws \Drupal\thron\Exception\InvalidCredentialException
   * @throws \Drupal\thron\Exception\NoCredentialException
   * @throws \Drupal\thron\Exception\NoPkeyException
   * @throws \Drupal\thron\Exception\UnableToConnectException
   */
  public function loginApp($configArgs = NULL);

  /**
   * @param bool $skip_cache
   *
   * @return array
   */
  public function getLoginData($skip_cache = FALSE);

  /**
   * Logins the Application as Platform User Impersonation
   * obtaining so the new `pkey`
   *
   * @return mixed|FALSE
   */
  public function impersonateApp();

  /**
   * @param string $xcontentId
   *
   * @return array
   */
  public function getContentDetail($xcontentId);

  /**
   * @return array
   * @throws \Exception
   */
  public function getContents();

  /**
   * @param string|int $timestamp
   *
   * @return array
   * @throws \Exception
   */
  public function getUpdatedContents($timestamp);

  /**
   * @return array
   * @deprecated not used anymore and will be removed soon
   */
  public function importMedia();

  /**
   * @param int $last_update
   *
   * @return array
   */
  public function updateMedia($last_update);

  /**
   * @return array
   */
  public function getClassifications();

  /**
   * @param $classificationId
   *
   * @return array
   */
  public function getTags($classificationId);

  /**
   * @param string $classification
   * @param array|NULL $filterOn
   * @param int|NULL $depth
   * @param string|NULL $search_text
   *
   * @return mixed
   */
  public function getTagsListByClassification($classification, $filterOn, $depth, $search_text);

  /**
   * @param $tagDefinitions
   * @param string $langcode
   *
   * @return array
   */
  public function filterTagsDefinitions($tagDefinitions, $langcode = NULL);

  /**
   * @param array $tag
   * @param bool $show_linked
   * @param bool $show_sub
   *
   * @return mixed
   */
  public function getTagDefinitionDetail($tag, $show_linked = FALSE, $show_sub = FALSE);

  /**
   * @return array
   */
  public function getVideoPlayerTemplatesList();

  /**
   * Retreives the Template characteristics.
   *
   * @param string $templateId The ID of Thron based template ID
   *
   * @return array
   */
  public function getVideoPlayerTemplateData($templateId);

  /**
   * @return bool
   */
  public function hasAccessToken();

  /**
   * @return array
   */
  public function getContentTypes();

  /**
   * @return string
   */
  public function getContentRealType($content);

  /**
   * @param $properties
   *
   * @return array
   */
  public function contentFindByProperties($properties);

  /**
   * Creates an embed code on the fly
   *
   * @param $templateId
   * @param $xcontentId
   * @param $uniqueId
   * @param $disguisedToken
   *
   * @return mixed
   */
  public function insertPlayerEmbedCode($templateId, $xcontentId, $uniqueId, $disguisedToken);

  /**
   * @param $array
   *
   * @return mixed
   */
  public function gluey($array);

  /**
   * @return void
   */
  public function resetCache();

  /**
   * Checks if current user has a Permission.
   *
   * @param string $permission
   *
   * @return boolean
   */
  public function hasPermission($permission);

  /**
   * @param $locale_data
   * @param string $lang
   * @param string $lang_key
   *
   * @return array
   */
  public function getSingleLocaleData($locale_data, $lang = NULL, $lang_key = 'lang');

  /**
   * @return string
   */
  public function getPreviewLanguage();

  /**
   * Get's the information about media.
   *
   * @param int $content_id
   * @param string|NULL $key
   * @return array|NULL
   */
  public function getMediaDetails($content_id, $key);

  /**
   * @return string|FALSE
   */
  public function getThronMediaPkey($content_id);

  /**
   * @return bool
   */
  public function setThronMediaPkey($content_id, $pkey);

  /**
   * Get's the Interval in secconds for the given cache expire amount.
   *
   * @param string|NULL $name
   *
   * @return int
   */
  public function getCacheInterval($name = NULL);
  
  /**
   * Get the breakpoint tags (if present)
   */
  public function getBreakpointTags($asMediaQueries=false);
}
