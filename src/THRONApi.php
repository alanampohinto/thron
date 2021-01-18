<?php

namespace Drupal\thron;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\thron\Exception\AppTokenExpiredException;
use Drupal\thron\Exception\InvalidCredentialException;
use Drupal\thron\Exception\NoCredentialException;
use Drupal\thron\Exception\NoPkeyException;
use Drupal\thron\Exception\THRONException;
use Drupal\thron\Exception\UnableToConnectException;
use Drupal\thron\Integration\Thronintegration_Api;

/**
 * Class THRONApi
 *
 * @package Drupal\thron
 */
class THRONApi implements THRONApiInterface {

  use StringTranslationTrait;

  /**
   * Permitted app type value.
   */
  const THRON_PERMITTED_APP_TYPE = 'CUSTOM';

  /**
   * Permitted app subtype value.
   */
  const THRON_PERMITTED_APP_SUBTYPE = 'APP-DRUPALCONNECTOR';

  /**
   * State key used to store last update timestamp.
   */
  const THRON_LAST_UPDATE_STATE_KEY = 'THRON_last_update';

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The current session's user account Interface.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * THRONApi constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   * @param \Drupal\Component\Datetime\TimeInterface $time
   * @param \Drupal\Core\Session\AccountInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              EntityTypeManagerInterface $entity_type_manager,
                              LoggerChannelInterface $logger,
                              StateInterface $state,
                              DateFormatterInterface $dateFormatter,
                              LanguageManagerInterface $languageManager,
                              CacheBackendInterface $cache,
                              TimeInterface $time,
                              AccountInterface $current_user
  ) {
	$this->config = $config_factory->get('thron.settings');
    $this->mediaStorage = $entity_type_manager->getStorage('media');
    $this->logger = $logger;
    $this->state = $state;
    $this->dateFormatter = $dateFormatter;
    $this->languageManager = $languageManager;
    $this->cache = $cache;
    $this->time = $time;
    $this->currentUser = $current_user;
  }

  /**
   * @param array $configArg
   *
   * @return array
   *
   * @throws \Drupal\thron\Exception\InvalidCredentialException
   * @throws \Drupal\thron\Exception\NoCredentialException
   * @throws \Drupal\thron\Exception\NoPkeyException
   * @throws \Drupal\thron\Exception\UnableToConnectException
   */
  public function loginApp($configArg = NULL) {
    if (!$configArg) {
      // Retrieve from config.
      if (!$this->config->get('client_id') || !$this->config->get('app_id') || !$this->config->get('app_key')) {
        throw new NoCredentialException();
      }
      $configArg = [
        'client_id' => $this->config->get('client_id'),
        'app_id' => $this->config->get('app_id'),
        'app_key' => $this->config->get('app_key'),
      ];
    }
    $res = Thronintegration_Api::performAppLogin($configArg['client_id'], $configArg['app_id'], $configArg['app_key']);

    // Check result.
    if (!$res || !isset($res->resultCode) || $res->resultCode != 'OK') {
      throw new UnableToConnectException();
    }

    // Check app type.
    if (!isset($res->app->appType) || $res->app->appType != self::THRON_PERMITTED_APP_TYPE) {
      throw new InvalidCredentialException('appType');
    }

    if (!isset($res->app->appSubType) || $res->app->appSubType != self::THRON_PERMITTED_APP_SUBTYPE) {
      throw new InvalidCredentialException('appSubType');
    }

    // Retrieve pKey.
    $pkey_arr = array_map(function ($obj) {
      return $obj->value;
    }, array_filter($res->app->metadata, function ($obj) {
      return $obj->name == 'pkey';
    }));

    if(count($pkey_arr) == 0) {
      throw new NoPkeyException();
    }
    $vals = array_values($pkey_arr);
    $pkey = array_shift($vals);

    // retrieve the tracking context
    $tracking_context_el = array_map(function ($obj) {
      return $obj->value;
    }, array_filter($res->app->metadata, function ($obj) {
      return $obj->name == 'tracking_context';
    }));

    if (count($tracking_context_el) == 0)
      $tracking_context = FALSE;
    else {
      $vals = array_values($tracking_context_el);
      $tracking_context = array_shift($vals);
    }

    // Build login data.
    $login_data = [
      'token' => $res->appUserTokenId,
      'pkey' => $pkey,
      'tracking_context' => $tracking_context,
      'rootCategoryId' => isset($res->app->rootCategoryId) ? $res->app->rootCategoryId : '',
    ];

	  $templates = array_filter($res->app->metadata, function ($item) {
      return $item->name == 'playerTemplates';
    });
    if (count($templates) > 0) {
      $templates = reset($templates);
      $login_data['default_player_templates'] = json_decode($templates->value, TRUE);
    }

    $imageSetTags = array_filter($res->app->metadata, function($item) {
      return $item->name == 'imageSetTag';
    });
    if (!empty($imageSetTags)) {
      $arrVal = reset($imageSetTags);
      $login_data['image_set_tag_info'] = json_decode($arrVal->value, TRUE);
    }

    return $login_data;
  }

  /**
   * @param bool $skip_cache
   *
   * @return array|bool
   */
  public function getLoginData($skip_cache = FALSE) {
    $cid = 'loginData_'.$this->config->get('client_id');
    if (!$skip_cache && $cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      $login_data = $this->loginApp();
      $this->cache->set($cid, $login_data, $this->time->getRequestTime() + $this->getCacheInterval('login'));
      return $login_data;

    } catch (THRONException $ex) {
      $ex->displayMessage();
      $ex->logException();
      $this->cache->delete($cid);
      return FALSE;
    }
  }

  /**
   * @param string $method_name
   * @param mixed $default_return
   * @param \Drupal\thron\Exception\THRONException $exception
   * @param array $params
   *
   * @return mixed
   */
  private function refreshAndRecall($method_name, $default_return, $exception, $params = []) {
    // Avoid recursion.
    $trace = $exception->getTrace();
    if ($trace[3]['function'] == 'refreshAndRecall') {
      $exception->displayMessage();
      $exception->logException();
      return $default_return;
    }

    if (!$login_data = $this->getLoginData(TRUE)) {
      return $default_return;
    }

    return call_user_func_array([$this, $method_name], $params);
  }

  /**
   * @param string $xcontentId
   * @param string|NULL $divArea
   *
   * @return array|bool|mixed|NULL
   */
  public function getContentDetailViaContentSearch($xcontentId, $divArea = NULL) {
    $cid = 'xcontent_search__'.$this->config->get('client_id')."§" . $xcontentId;
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $data = Thronintegration_Api::getContentDetailViaContentSearch($this->config->get('client_id'), $login_data['token'], $xcontentId, $divArea);
      if ($data->resultCode != 'OK') {
        if(isset($data->errorDescription))
          throw new \Exception($data->errorDescription);
        else
          throw new \Exception("An unknown problem occurred while invoking getContentDetailViaContentSearch");
      }

      $this->cache->set($cid, $data, $this->time->getRequestTime() + $this->getCacheInterval());
      return $data;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getContentDetailViaContentSearch', FALSE, $ex, [$xcontentId, $divArea]);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return FALSE;
    }
  }

  /**
   * @param string $xcontentId
   * @param string|NULL $divArea
   *
   * @return array|bool|mixed|NULL
   */
  public function getContentDetail($xcontentId, $divArea = NULL) {
    $cid = 'xcontent__'.$this->config->get('client_id')."§" . $xcontentId;
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $data = Thronintegration_Api::contentDetail($this->config->get('client_id'), $login_data['token'], $xcontentId, $divArea);
      if ($data->resultCode != 'OK') {
        if(isset($data->errorDescription))
          throw new \Exception($data->errorDescription);
        else
          throw new \Exception("An unknown problem occurred while invoking contentDetail");
      }

      $this->cache->set($cid, $data->content, $this->time->getRequestTime() + $this->getCacheInterval());
      return $data->content;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getContentDetail', FALSE, $ex, [$xcontentId, $divArea]);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return FALSE;
    }
  }

  /**
   * Recursive function that iterates tags of a classification and gets in plain array
   *
   * @param $classification
   *
   * @param array $filterOn
   * @param int $depth
   * @param NULL $search_text
   *
   * @return mixed|void
   * @throws \Exception
   */
  public function getTagsListByClassification($classification, $filterOn = [], $depth = 1, $search_text = NULL) {
    if (!$classification) {
      return NULL;
    }

    $cid = "classification_tags__{$classification}_".$this->config->get('client_id');
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $plain_data = [];
      $showChilds = $depth === -1 || $depth > 0;
      $upper_language = strtoupper($this->languageManager->getCurrentLanguage()->getId());
      if(strpos($upper_language, "_") != false) {
        $upper_language = explode("_", $upper_language)[0];
      }

      $data = Thronintegration_Api::tagDefinitionList($this->config->get('client_id'), $login_data['token'], $classification, $filterOn, TRUE, $showChilds, $depth, $search_text, $upper_language);

      // Check result.
      if ($data['status'] != 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      // Retrieve tags.
      foreach ($data['tags'] as $tag_id => $tag) {
        // base tag definition.
        $names = array_filter($tag['names'], function($item) use($upper_language) {
          return strtoupper($item['lang']) == $upper_language;
        });
        if (empty($names)) {
          $names = array_filter($tag['names'], function($item) {
            return strtoupper($item['lang']) == 'EN';
          });
        }
        $plain_data[$tag_id] = [
          'id' => $tag_id,
          'classification' => $classification,
          'name' => reset($names)['label'],
        ];
      }

      $this->cache->set($cid, $plain_data, $this->time->getRequestTime() + $this->getCacheInterval());
      return $plain_data;

    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getTagsListByClassification', NULL, $ex, [$classification, $filterOn, $depth, $search_text]);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return [];
    }
  }

  /**
   * @param $tagDefinitions
   * @param string $langcode
   *
   * @return array
   */
  public function filterTagsDefinitions($tagDefinitions, $langcode = 'EN') {
    $classifications = $this->config->get('classifications');

    $tags = [];
    foreach ($tagDefinitions as $tagDefinition) {
      if(!isset($tagDefinition->classificationId)) $tagDefinition=json_decode(json_encode($tagDefinition), FALSE);
      if ($classifications[$tagDefinition->classificationId]) {
        if (isset($tagDefinition->names)) {
          foreach ($tagDefinition->names as $name) {
            if ($name->lang == $langcode) {
              break;
            }
          }
        }

        $tags[$tagDefinition->id] = [
          'id' => $tagDefinition->id,
          'name' => isset($name) ? $name->label : NULL,
          'classificationId' => $tagDefinition->classificationId,
        ];
      }
    }

    return $tags;
  }

  /**
   * @param array $tag
   *
   * @return bool|mixed
   */
  public function getTagDefinitionDetail($tag, $show_linked = TRUE, $show_sub = FALSE) {
    if (!isset($tag['classificationId']) || !isset($tag['id'])) {
      return FALSE;
    }

    $cid = 'tag_definition_detail__' .$this->config->get('client_id')."_". $tag['classificationId'] . '_' . $tag['id'];
    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
      return $data['item'];
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $data = Thronintegration_Api::getITagDefinitionDetail($this->config->get('client_id'), $login_data['token'], $tag['classificationId'], $tag['id'], $show_linked, $show_sub);
      if ($data['status'] !== 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      $this->cache->set($cid, $data, $this->time->getRequestTime() + $this->getCacheInterval());
      return $data['item'];
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getTagDefinitionDetail', FALSE, $ex, [$tag]);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return FALSE;
    }
  }

  /**
   * @return array|bool
   */
  public function getClassifications() {
    $classifications = [];

    $cid = 'classifications_'.$this->config->get('client_id');
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $data = Thronintegration_Api::classificationList($this->config->get('client_id'), $login_data['token'], TRUE);
      // Check status.
      if ($data['status'] !== 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      foreach ($data['classifications'] as $classification) {
        $classifications[$classification['id']] = $classification;
      }

      $this->cache->set($cid, $classifications, $this->time->getRequestTime() + $this->getCacheInterval());
      return $classifications;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getClassifications', FALSE, $ex);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return FALSE;
    }
  }

  /**
   * @param array $ids
   *
   * @param bool $excludeLevelHigherThan
   *
   * @return false|mixed
   */
  public function getCategories($ids = [], $excludeLevelHigherThan = FALSE) {
    $cid = 'categories_list_' . (empty($ids) ? 'all' : md5(implode($ids)));

    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }
    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $data = Thronintegration_Api::categoriesList($this->config->get('client_id'), $login_data['token'], $ids, $excludeLevelHigherThan);
      // Check status.
      //if ($data['status'] !== 'OK') {
      //  throw new \Exception($data['errorDescription']);
      //}

      foreach ($data['categories'] as $category) {
        $categories[$category['category']['id']] = $category['category'];
      }

      $this->cache->set($cid, $categories, $this->time->getRequestTime() + $this->getCacheInterval());
      return $categories;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('categoriesList', FALSE, $ex);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return FALSE;
    }
  }

  /**
   * @param $classificationId
   *
   * @return array|bool
   */
  public function getTags($classificationId) {
    if (!$classificationId) {
      return [];
    }

    $cid = 'classification_' .$this->config->get('client_id')."_${classificationId}_tags";
    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
      return $data['tags'];
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $data = Thronintegration_Api::tagDefinitionList($this->config->get('client_id'), $login_data['token'], $classificationId, FALSE, TRUE, TRUE);
      if ($data['status'] !== 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      $this->cache->set($cid, $data, $this->time->getRequestTime() + $this->getCacheInterval());
      return $data['tags'];
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getTags', FALSE, $ex, [$classificationId]);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return FALSE;
    }
  }

  /**
   * @return string|FALSE
   */
  public function getThronMediaEmbedId($content_id, $node_id, $templateId) {
    $query = $this->mediaStorage->getQuery()
      ->condition('bundle', 'thron_with_media_source')
      ->condition('field_thron_id', $content_id);
    $res = $query->execute();
    if (empty($res)) {
      return FALSE;
    }
    $obj = $this->mediaStorage->load(reset($res));
  	try {
      $templateIds=json_decode($obj->get('field_thron_embed_ids')->value, TRUE);

      // detect the save format for this field and update it if needed
      if(empty($templateIds)) return FALSE;
      if(count($templateIds)>0) {
        if(!isset($templateIds[0]["node_id"])) {
          $newTemplates = [];
          foreach($templateIds as $tid=>$eid) {
            array_push($newTemplates, ["node_id"=>FALSE, "template_id"=>$tid, "embed_code_id"=>$eid]);
          }
          $templateIds = $newTemplates;
        }
      }
      $filtered = array_filter($templateIds, function($obj) use($node_id, $templateId) {
        return $obj["template_id"] === $templateId && $obj["node_id"] === $node_id;
      });
      if(count($filtered) == 0) {
        $filtered = array_filter($templateIds, function($obj) use($templateId) {
          return $obj["template_id"] === $templateId;
        });
      }

      if(count($filtered) == 0) return FALSE;
      $filtered = array_shift($filtered);
      return $filtered["embed_code_id"];
    } catch(\Exception $e) {
      return FALSE;
    }
  }

  /**
   * @return bool
   */
  public function setThronMediaEmbedId($content_id, $node_id, $templateId, $embedCodeId) {
    $query = $this->mediaStorage->getQuery()
      ->condition('bundle', 'thron_with_media_source')
      ->condition('field_thron_id', $content_id);
    $res = $query->execute();
    if (empty($res)) {
      return FALSE;
    }
    $obj = $this->mediaStorage->load(reset($res));
    $templateIds=json_decode($obj->get('field_thron_embed_ids')->value, TRUE);
    if(empty($templateIds)) $templateIds=[];

    // detect the save format for this field and update it if needed
    if(count($templateIds)>0) {
      if(!isset($templateIds[0]["node_id"])) {
        $newTemplates = [];
        foreach($templateIds as $tid=>$eid) {
          array_push($newTemplates, ["node_id"=>FALSE, "template_id"=>$tid, "embed_code_id"=>$eid]);
        }
        $templateIds = $newTemplates;

        $obj->set('field_thron_embed_ids', json_encode($templateIds));
        try {
          $obj->save();
        } catch (EntityStorageException $e) {
          // look into it
        }
      }

    }

    $newTemplates=[];
    $willUpdate=FALSE;
	  $templateFound=FALSE;

    // loop through the templates array
    foreach($templateIds as $template) {
      // is this embed code id linked to the same template?
      if($template["template_id"] === $templateId) {
        // is this node the same?
        if($template["node_id"] === $node_id) {
          // is the embed code ID the same?
          if($template["embed_code_id"] === $embedCodeId) {
            // copy this as-is
            array_push($newTemplates, $template);
		      	$templateFound=TRUE;
          } else {
            // we can modify this
            $template["embed_code_id"] = $embedCodeId;
            array_push($newTemplates, $template);
            $willUpdate = TRUE;
      			$templateFound=TRUE;
          }

        } elseif($template["node_id"] == FALSE) {
          // we can modify this
          $template["node_id"] = $node_id;
          array_push($newTemplates, $template);
          $willUpdate = TRUE;
	    	  $templateFound=TRUE;
        } else {
          // this embed code refers to a different page
          array_push($newTemplates, $template);
        }
      } else
        array_push($newTemplates, $template);
    }

    if(!$templateFound) {
      array_push($newTemplates, ["node_id"=>$node_id, "template_id"=>$templateId, "embed_code_id"=>$embedCodeId]);
      $willUpdate = TRUE;
    }

    if($willUpdate) {
      $obj->set('field_thron_embed_ids', json_encode($newTemplates));
      try {
        $obj->save();
        return TRUE;
      } catch (EntityStorageException $e) {
        return FALSE;
      }
    } else
      return TRUE;
  }

  /**
   * @return bool|array
   */
  public function getVideoPlayerTemplatesList() {
    $cid = 'player_templates_'.$this->config->get('client_id');
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        $ret = FALSE;
        throw new \Exception('LoginApp error');
      }

      $ret = [
        'templates' => [],
        'default_templates' => [],
      ];

      $data = Thronintegration_Api::getPlayerCustomTemplates($this->config->get('client_id'), $login_data['token'], 0);

      if ($data['resultCode'] !== 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      foreach ($data['items'] as $template)
        $ret['templates'][$template['id']] = $template;

      if(isset($login_data['default_player_templates'])) {
        if(isset($login_data['default_player_templates']['default']))
          if(array_key_exists($login_data['default_player_templates']['default'], $ret['templates']))
            $ret['default_templates']['default'] = $login_data['default_player_templates']['default'];

        if(isset($login_data['default_player_templates']['noSkin']))
          if(array_key_exists($login_data['default_player_templates']['noSkin'], $ret['templates']))
            $ret['default_templates']['noSkin'] = $login_data['default_player_templates']['noSkin'];
      }

      $this->cache->set($cid, $ret, $this->time->getRequestTime() + $this->getCacheInterval());
      return $ret;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getVideoPlayerTemplatesList', $ret, $ex);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return $ret;
    }
  }

  /**
   * @param string $templateId
   *
   * @return array|NULL
   */
  public function getVideoPlayerTemplateData($templateId) {
    $cid = 'player_template__'.$this->config->get('client_id')."_" . $templateId;
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $data = Thronintegration_Api::getPlayerCustomTemplateData($this->config->get('client_id'), $login_data['token'], $templateId);

      if ($data['resultCode'] !== 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      $this->cache->set($cid, $data, $this->time->getRequestTime() + $this->getCacheInterval());
      return $data['item'];
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getVideoPlayerTemplateData', NULL, $ex, [$templateId]);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return NULL;
    }
  }

  /**
   * @param $templateId
   * @param templateLabel
   * @param context
   * @param $xcontentId
   *
   * @return array|mixed|NULL
   * @throws \Exception
   */
  public function insertPlayerEmbedCode($templateId, $templateLabel, $context, $xcontentId) {
    $cid = 'player_embedcode__'.$this->config->get('client_id')."_${templateId}__" . preg_replace('/-/', '_', $xcontentId);
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $values = [];
      $skipPkeyCreation = TRUE;
      $source = ['id' => $xcontentId, 'type' => 'CONTENT'];
      $sitename = "";
      try {
        $sitename = \Drupal::config('system.site')->get('name');
      } catch(\Exception $ex) {};

      if($sitename)
        $embedName = "Drupal embed on $sitename (template $templateLabel)";
      else
        $embedName = "Drupal embed (template $templateLabel)";

      $data = Thronintegration_Api::insertEmbedCode($this->config->get('client_id'), $login_data['token'], $embedName, $source, $context, $templateId, 'CUSTOM', $values, $skipPkeyCreation);
      if ($data['resultCode'] !== 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      $this->cache->set($cid, $data, $this->time->getRequestTime() + $this->getCacheInterval());
      return $data;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('insertPlayerEmbedCode', NULL, $ex, [$templateId, $templateLabel, $context, $xcontentId, $login_data['token']]);
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccessToken() {
    if (!$login_data = $this->getLoginData()) {
      return FALSE;
    }

    return isset($login_data['token']) && !empty($login_data['token']);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentTypes() {
    return [
      'IMAGE' => $this->t('Image')->__toString(),
      'VIDEO' => $this->t('Video')->__toString(),
      'OTHER' => $this->t('Document')->__toString(),
      'AUDIO' => $this->t('Audio')->__toString(),
      'PLAYLIST_GALLERY' => $this->t('Image Gallery')->__toString(),
      'PLAYLIST_360' => $this->t('360° Gallery')->__toString(),
      'PLAYLIST' => $this->t('Playlist')->__toString(),
      'URL' => $this->t('Url')->__toString(),
      'PAGELET' => $this->t('Pagelet')->__toString(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getContentRealType($content) {
    if(!isset($content->contentType))
      $content=json_decode(json_encode($content), FALSE);

    if ($content->contentType == 'PLAYLIST') {
      $is_gallery = FALSE;
      $is_360 = FALSE;
      if(isset($content->metadatas)) {
        foreach ($content->metadatas as $metadata) {
          if ($metadata->name == '_PLAYLISTTEMPLATE_' && $metadata->value == 'IMAGE') {
            $is_gallery = TRUE;

          }
          elseif ($metadata->name == '_VIEWMODE_' && $metadata->value == '360') {
            $is_360 = TRUE;
          }
        }
        // Return real type.
        if ($is_gallery) {
          if ($is_360) {
            return '360';
          }
          return 'GALLERY';
        }
      } else {
        if(isset($content->details->playlistDetails)) {
          $els = array_filter($content->details->playlistDetails, function($obj) {
            return $obj->name == "_PLAYLISTTEMPLATE_";
          });

          if(count($els)>0) {
            $els = array_values($els);
            $meta_value = $els[0]->value;
            if($meta_value == "IMAGE")
              $is_gallery = TRUE;
          }

          $els = array_filter($content->details->playlistDetails, function($obj) {
            return $obj->name == "_VIEWMODE_";
          });

          if(count($els)>0) {
            $els = array_values($els);
            $meta_value = $els[0]->value;
            if($meta_value == "360")
              $is_360 = TRUE;
          }

          // Return real type.
          if ($is_gallery) {
            if ($is_360) {
              return '360';
            }
            return 'GALLERY';
          }
        }
      }
    }
    return $content->contentType;
  }

  /**
   * @param $properties
   *
   * @return array|mixed|NULL
   */
  public function contentSearch($properties) {
    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $upper_language = strtoupper($this->languageManager->getCurrentLanguage()->getId());
      if(strpos($upper_language, "_") != false) {
        $upper_language = explode("_", $upper_language)[0];
      }
      if(isset($properties['keyword'])){
        if(strpos($properties['keyword'], 'id:') !== false){
          $keywords = explode('id:',$properties['keyword']);
          $id = $keywords[1];
          $id = str_replace(' ', '',$id);
        }
      }

      // Call the API endpoint.
      $data = Thronintegration_Api::contentSearch(
        $this->config->get('client_id'),
        $login_data['token'],
        isset($properties['linkedCategories']) ? $properties['linkedCategories'] : $login_data['rootCategoryId'],
        isset($properties['nextPage']) ? $properties['nextPage'] : NULL,
        !empty($properties['contentType']) ? $properties['contentType'] : FALSE,
        isset($properties['divArea']) ? $properties['divArea'] : FALSE,
        isset($properties['tags']) ? $properties['tags'] : FALSE,
        isset($properties['keyword']) ? $properties['keyword'] : FALSE,
        isset($properties['orderBy']) ? $properties['orderBy'] : FALSE,
        TRUE,
        isset($properties['langcode']) ? $properties['langcode'] : $upper_language,
        isset($id) ? $id : NULL
      );
      if ($data['resultCode'] !== 'OK') {
        throw new \Exception($data['errorDescription']);
      }
      //$this->cache->set($cid, $data, $this->time->getRequestTime() + $this->getCacheInterval());

      $tagsWithName = $this->getTagsWithName($data, $login_data['token']);
      if (!empty($tagsWithName)) {
        foreach ($data["contents"] as $cIndex => $content) {
          if (isset($content->details)) {
            foreach ($content->details->itags as $tIndex => $tags) {
              $locale = $tagsWithName[$tags->classificationId][$tags->id];
              $data["contents"][$cIndex]->details->itags[$tIndex]->locale = $locale;

            }
          }
        }
      }
      return $data;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('contentSearch', NULL, $ex, [$properties]);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function gluey($entry) {
    return is_array($entry) ? implode('', array_map([$this, 'gluey'], $entry)) : $entry;
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache() {
    $this->cache->deleteAll();
  }

  /**
   * {@inheritDoc}
   */
  public function hasPermission($permission) {
    return $this->currentUser->hasPermission($permission);
  }

  /**
   * {@inheritDoc}
   */
  public function getSingleLocaleData($locale_data, $lang = NULL, $lang_key = 'lang') {
    if (empty($lang)) {
      $lang = $this->getPreviewLanguage();
    }
    $default = (array) reset($locale_data);
    foreach ($locale_data as $locale_datum) {
      $locale_datum = (array) $locale_datum;
      if ($locale_datum[$lang_key] == $lang) {
        return $locale_datum;
      }
      if ($locale_datum[$lang_key] == 'EN') {
        $default = $locale_datum;
      }
    }
    return $default;
  }

  /**
   * {@inheritDoc}
   */
  public function getPreviewLanguage() {
    return $this->config->get('preview_language') ?: 'EN';
  }

  /**
   * Returns the details about media.
   *
   * @param $content_id
   * @param NULL $key
   *
   * @return array|FALSE
   */
  public function getMediaDetails($content_id, $key = NULL, $divArea = NULL) {
    $cid = 'mediaDetails_'.$this->config->get('client_id')."_". md5($content_id);
    if ($key) { $cid .= ':' . $key; }
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

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

      $data = Thronintegration_Api::contentSearchLite($this->config->get('client_id'), $login_data['token'], $search_param);
      $ret = [];
      if ($key) {
        $ret = array_map(function($obj) use($key) {
          return $obj["details"][$key];
        }, $data['items']);
      }
      else {
        $ret = $data['items'];
      }
      $this->cache->set($cid, $ret, $this->time->getRequestTime() + $this->getCacheInterval());
      return $ret;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getMediaDetails', NULL, $ex, [$content_id, $key]);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return FALSE;
    }
  }

  /**
   * Returns the Interval for the given cache expire amount (in seconds).
   *
   * @param string|NULL $name
   *   The interval name to retrieve.
   *
   * @return int
   */
  public function getCacheInterval($name = NULL) {
    switch ($name) {
      case 'login':
        return $this->config->get('login_cache_max_age') ?:
          THRON_LOGIN_CACHE_MAX_AGE;

      default:
        return $this->config->get('cache_max_age') ?:
          THRON_CACHE_MAX_AGE;
    }
  }

  /**
   * Get the breakpoint tags (if present)
   */
  public function getBreakpointTags($asMediaQueries=FALSE) {
    try {
      $obj = [];
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }
      if (isset($login_data['image_set_tag_info']) && !empty($login_data['image_set_tag_info'])) {
        $parent_tag_data = $this->getTagDefinitionDetail($login_data['image_set_tag_info'], FALSE, TRUE);
        $sub_nodes = $parent_tag_data['subNodeIds'];
        if (!empty($sub_nodes)) {
          foreach ($sub_nodes as $tag_id) {
            $tag = [
              'classificationId' => $login_data['image_set_tag_info']['classificationId'],
              'id' => $tag_id,
            ];
            $tag_data = $this->getTagDefinitionDetail($tag, FALSE, FALSE);
            $obj[$tag_data['id']] = [
              'pretty-id' => $tag_data['prettyId'],
            ];
          }
        }
      }

      if($asMediaQueries) {
        $ret=[];
        foreach($obj as $k=>$v) {
          if($v["pretty-id"] == "image-set-master")
            $ret["default"] = $k;
          $ret[$k] = ["name"=>$v["pretty-id"], "value"=>"*"];
        }
        return $ret;
      } else
        return $obj;
    } catch(\Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Get the element's value or the default
   */
  public function getValueOrDefault($el, $default) {
    return empty($el) ? $default : $el;
  }

  /**
   * @param $data
   * @param $token
   * @throws AppTokenExpiredException
   */
  public function getTagsWithName(array $data, string $token)
  {
    $tagsList = [];
    foreach ($data['contents'] as $content) {
      if (isset($content->details)) {
        foreach ($content->details->itags as $tags) {
          $tagsList[$tags->classificationId][] = $tags->id;
        }
      }
    }
    $tagsWithName = [];
    foreach ($tagsList as $classificationId => $tagsId) {
      $tagsIdString = implode(array_unique($tagsId, SORT_STRING));
      $cid = 'xintelligence_itagdefinition__' . $this->config->get('client_id') . "§" . $classificationId . $tagsIdString;
      if ($cache = $this->cache->get($cid)) {
        $tagsWithName = array_merge($tagsWithName, $cache->data);
      } else {
        try {
          $tag = Thronintegration_Api::tagDefinitionList(
            $this->config->get('client_id'),
            $token,
            $classificationId,
            $tagsId,
            TRUE,
            TRUE
          );
        } catch (AppTokenExpiredException $ex) {
          throw $ex;
        }

        if ($tag['status'] == 'OK') {
          foreach ($tag["tags"] as $id => $value) {
            $tagsWithName[$classificationId][$id] = $value['names'];
          }
          if (!$this->cache->get($cid)) {
            $this->cache->set($cid, $tagsWithName, $this->time->getRequestTime() + $this->getCacheInterval());
          }
        }
      }
    }
    return $tagsWithName;
  }
}
