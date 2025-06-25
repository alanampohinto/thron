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
   * @param string $xcontentId
   * @param string|NULL $divArea
   *
   * @return array
   */
  public function getContentDetailViaContentSearch($xcontentId, $divArea = NULL);

  /**
   * @param string $xcontentId
   * @param string|NULL $divArea
   *
   * @return array
   */
  public function getContentDetail($xcontentId, $divArea = NULL);

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
   * Retrieve the Template characteristics.
   *
   * @param string $templateId The ID of THRON based template ID
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
  public function contentSearch($properties);

  /**
   * Creates an embed code on the fly
   *
   * @param $templateId
   * @param $templateLabel
   * @param $context
   * @param $xcontentId
   *
   * @return mixed
   */
  public function insertPlayerEmbedCode($templateId, $templateLabel, $context, $xcontentId);

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
   * @return string
   */
  public function getFullPreviewLanguage();

  /**
   * Returns the details about media.
   *
   * @param int $content_id
   * @param string|NULL $key
   * @return array|NULL
   */
  public function getMediaDetails($content_id, $key);

  /**
   * @return string|FALSE
   */
  public function getThronMediaEmbedId($content_id, $node_id, $templateId);

  /**
   * @return bool
   */
  public function setThronMediaEmbedId($content_id, $node_id, $templateId, $embedCodeId);

  /**
   * Returns the Interval in seconds for the given cache expire amount.
   *
   * @param string|NULL $name
   *
   * @return int
   */
  public function getCacheInterval($name = NULL);
  
  /**
   * Get the breakpoint tags (if present)
   */
  public function getBreakpointTags($asMediaQueries=FALSE);

  /**
   * Get the element's value or the default
   */
  public function getValueOrDefault($el, $default);
}
