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
use Drupal\thron\Exception\ThronException;
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
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

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
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
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
    $pkey = array_map(function ($obj) {
      return $obj->value;
    }, array_filter($res->app->metadata, function ($obj) {
      return $obj->name == 'pkey';
    }))[0];

    if (!isset($pkey) || empty($pkey)) {
      throw new NoPkeyException();
    }

    // Build login data.
    $login_data = [
      'token' => $res->appUserTokenId,
      'pkey' => $pkey,
      'disguise_username' => $res->app->canDisguise ? reset($res->app->disguiseData->usersWhiteList) : FALSE,
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
    $cid = 'loginData';
    if (!$skip_cache && $cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      $login_data = $this->loginApp();
      $this->cache->set($cid, $login_data, $this->time->getRequestTime() + $this->getCacheInterval('login'));
      return $login_data;

    } catch (ThronException $ex) {
      $ex->displayMessage();
      $ex->logException();
      $this->cache->delete($cid);
      return FALSE;
    }
  }

  /**
   * @param string $method_name
   * @param mixed $default_return
   * @param \Drupal\thron\Exception\ThronException $exception
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
   * This call is NOT CACHED!
   *
   * @return array|bool|FALSE|mixed|string|null
   * @throws \Exception
   */
  public function impersonateApp() {
    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $res = Thronintegration_Api::performAppSu($this->config->get('client_id'), $this->config->get('app_id'), $this->config->get('app_key'), $login_data['disguise_username']);

      if ($res) {
        if (preg_match('/[a-f0-9]{8}\-[a-f0-9]{4}\-4[a-f0-9]{3}\-[a-f0-9]{4}\-[a-f0-9]{12}/', $res)) {
          return $res;
        }

        $this->logger->error('App impersonation error: @error', ['@error' => $res]);
        return FALSE;
      }

      $this->logger->error('App impersonation error: @error', ['@error' => 'Something went wrong!']);
      return FALSE;
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return FALSE;
    }
  }

  /**
   * @param string $xcontentId
   *
   * @return array|bool|mixed|null
   */
  public function getContentDetail($xcontentId) {
    $cid = 'xcontent__' . $xcontentId;
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $data = Thronintegration_Api::getContentDetail($this->config->get('client_id'), $login_data['token'], $login_data['pkey'], $xcontentId);
      if ($data->resultCode != 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      $this->cache->set($cid, $data, $this->time->getRequestTime() + $this->getCacheInterval());
      return $data;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getContentDetail', FALSE, $ex, [$xcontentId]);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return FALSE;
    }
  }

  /**
   * @return array|bool|null
   *
   * @deprecated no more used.
   */
  public function getContents() {
    $cid = 'syncExport_contents';
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $contents = [];
      $next_page = NULL;
      do {
        $data = Thronintegration_Api::syncExport($this->config->get('client_id'), $login_data['token'], [$login_data['rootCategoryId']], [], 0, $next_page);

        if (!isset($data->resultCode) || $data->resultCode !== 'OK') {
          $this->logger->error($data->errorDescription);
          return FALSE;
        }

        foreach ($data->items as $item) {
          $locales = array_filter($item->content->locales, function ($obj) {
            return $obj->locale == 'EN';
          });
          $first_locale = !empty($locales) ? reset($locales) : reset($item->content->locales);
          $contents[] = [
            'id' => $item->content->id,
            'name' => $first_locale->name,
            'description' => isset($first_locale->description) ? $first_locale->description : '',
            'tags' => $this->filterTagsDefinitions($item->itagDefinitions),
          ];
        }

        // Check if there are more results.
        $next_page = isset($data->nextPage) ? $data->nextPage : NULL;

      } while (isset($next_page));

      $this->cache->set($cid, $contents, $this->time->getRequestTime() + $this->getCacheInterval());
      return $contents;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getContents', FALSE, $ex);
    }
    catch (\Exception $ex) {
      $this->logger->error($ex->getMessage());
      return FALSE;
    }
  }

  /**
   * @param int $timestamp
   *
   * @return array|bool
   *
   * @deprecated No more used.
   */
  public function getUpdatedContents($timestamp) {
    $contents = [];
    $fromDate = $this->dateFormatter->format($timestamp, 'custom', 'c');

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $next_page = NULL;
      do {
        $data = Thronintegration_Api::syncUpdatedContent($this->config->get('client_id'), $login_data['token'], $fromDate, NULL, [$login_data['rootCategoryId']], [], 0, $next_page);

        if (!isset($data->resultCode) || $data->resultCode !== 'OK') {
          $this->logger->error($data->errorDescription);
          return FALSE;
        }

        foreach ($data->items as $item) {
          $locales = array_filter($item->content->locales, function ($obj) {
            return $obj->locale == 'EN';
          });
          $first_locale = !empty($locales) ? reset($locales) : reset($item->content->locales);

          $is_categorized = FALSE;
          foreach ($item->linkedCategories as $linkedCategory) {
            $is_categorized = $linkedCategory->id == $login_data['rootCategoryId'] || $is_categorized;
          }

          $contents[] = [
            'id' => $item->content->id,
            'name' => $first_locale ? $first_locale->name : $item->content->id,
            'description' => $first_locale && isset($first_locale->description) ? $first_locale->description : '',
            'removed' => $item->removed || !$is_categorized,
            'tags' => $this->filterTagsDefinitions($item->itagDefinitions),
          ];
        }
        // Check if there are more results.
        $next_page = isset($data->nextPage) ? $data->nextPage : NULL;

      }
      while (isset($next_page));

      return $contents;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('getUpdatedContents', FALSE, $ex, [$timestamp]);
    }
    catch(\Exception $ex) {
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
   * @param null $search_text
   *
   * @return mixed|void
   * @throws \Exception
   */
  public function getTagsListByClassification($classification, $filterOn = [], $depth = 1, $search_text = NULL) {
    if (!$classification) {
      return NULL;
    }

    $cid = "classification_tags__{$classification}";
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

      $data = Thronintegration_Api::tagDefinitionList($this->config->get('client_id'), $login_data['token'], $classification, $filterOn, TRUE, $showChilds, $depth, $search_text, $upper_language);

      // Check result.
      if ($data['status'] != 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      // Retrieve tags.
      foreach ($data['tags'] as $tag_id => $tag) {
        // base tag definition.
        $names = array_filter($tag['names'], function($item) {
          $upper_language = strtoupper(\Drupal::languageManager()
            ->getCurrentLanguage()->getId());
          return $item['lang'] == $upper_language;
        });
        if (empty($names)) {
          $names = array_filter($tag['names'], function($item) {
            $upper_language = strtoupper(\Drupal::languageManager()
              ->getCurrentLanguage()->getId());
            return $item['lang'] == 'EN';
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

    $cid = 'tag_definition_detail__' . $tag['classificationId'] . '_' . $tag['id'];
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

    $cid = 'classifications';
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
   * @param $classificationId
   *
   * @return array|bool
   */
  public function getTags($classificationId) {
    if (!$classificationId) {
      return [];
    }

    $cid = 'classification_' . $classificationId . '_tags';
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
   * @param string $mid
   *
   * @return bool|mixed
   */
  private function checkExistingThronMedia($mid) {
    $query = $this->mediaStorage->getQuery()
      ->condition('bundle', 'thron_with_media_source')
      ->condition('field_thron_id', $mid);
    $res = $query->execute();
    if (empty($res)) {
      return FALSE;
    }
    return reset($res);
  }

  /**
   * @return bool|array
   */
  public function getVideoPlayerTemplatesList() {
    $cid = 'player_templates';
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        $ret = FALSE;
        throw new \Exception('LoginApp error');
      }

      $ret = [
        'default_templates' => [
          'default' => $login_data['default_player_templates']['default'],
          'noSkin' => $login_data['default_player_templates']['noSkin'],
        ],
        'templates' => [],
      ];

      $data = Thronintegration_Api::getPlayerCustomTemplates($this->config->get('client_id'), $login_data['token'], 0);

      if ($data['resultCode'] !== 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      foreach ($data['items'] as $template) {
        $ret['templates'][$template['id']] = $template;
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
   * @return array|null
   */
  public function getVideoPlayerTemplateData($templateId) {
    $cid = 'player_template__' . $templateId;
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
   * @param $xcontentId
   * @param $uniqueId
   *
   * @return array|mixed|null
   * @throws \Exception
   */
  public function insertPlayerEmbedCode($templateId, $xcontentId, $uniqueId, $disguisedToken) {
    $cid = 'player_embedcode__' . $templateId . '__' . preg_replace('/-/', '_', $xcontentId);
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $values = [];
      $secure = FALSE;
      $source = ['id' => $xcontentId, 'type' => 'CONTENT'];
      $embedName = 'Random usage on Drupal ' . $uniqueId; // TODO: make this more informative

      $data = Thronintegration_Api::insertEmbedCode($this->config->get('client_id'), $disguisedToken, $embedName, $source, FALSE, $templateId, 'CUSTOM', $values, $secure);

      if ($data['resultCode'] !== 'OK') {
        throw new \Exception($data['errorDescription']);
      }

      $this->cache->set($cid, $data, $this->time->getRequestTime() + $this->getCacheInterval());
      return $data;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('insertPlayerEmbedCode', NULL, $ex, [$templateId, $xcontentId, $uniqueId, $disguisedToken]);
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return NULL;
    }
  }

  /**
   * @return array
   *
   * @deprecated not used anymore and will be removed soon
   */
  public function importMedia() {
    $ret = ['created' => 0, 'skipped' => 0, 'errors' => 0];
    $thronMedias = $this->getContents();

    if ($thronMedias === FALSE) {
      $this->logger->error('An error occurred retrieving medias.');
      $ret['errors']++;
      return $ret;
    }

    foreach ($thronMedias as $k => $v) {
      if ($media_id = $this->checkExistingThronMedia($v['id'])) {
        $ret['skipped']++;
      }
      else {
        try {
          $media = $this->mediaStorage->create([
            'bundle' => 'thron_with_media_source',
            'name' => $v['name'],
            'field_thron_id' => [
              'value' => $v['id'],
            ],
          ]);
          // $media->set('field_tags', $media_tags);
          $media->save();
          $ret['created']++;
        } catch (\Exception $e) {
          $ret['errors']++;
          $this->logger->error('Cannot import media -' . $e->getMessage());
        }
      }
    }

    $this->state->set(self::THRON_LAST_UPDATE_STATE_KEY, $this->time->getRequestTime());
    return $ret;
  }

  /**
   * @param int $last_update
   *  Timestamp.
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @deprecated No longer needed! Use for tests or devel ops only!
   */
  public function updateMedia($last_update) {
    $ret = ['deleted' => 0, 'errors' => 0];

    if (!$last_update) {
      $this->logger->error('Invalid Last update timestamp.');
      $ret['errors']++;
      return $ret;
    }

    $thronMediasToUpdate = $this->getUpdatedContents($last_update);

    if ($thronMediasToUpdate === FALSE) {
      $this->logger->error('An error occurred retrieving medias.');
      $ret['errors']++;
      return $ret;
    }

    foreach ($thronMediasToUpdate as $k => $v) {
      if (!$v['removed']) {
        continue;
      }

      $media_id = $this->checkExistingThronMedia($v['id']);
      /** @var \Drupal\media\Entity\Media $media */
      $media = $media_id ? $this->mediaStorage->load($media_id) : NULL;
      if ($media) {
        // Delete.
        $media->delete();
        $cid = 'xcontent__' . $v['id'];
        $this->cache->delete($cid);
        $ret['deleted']++;
      }
    }

    // Updating the last update timestamp
    $this->state->set(self::THRON_LAST_UPDATE_STATE_KEY, $this->time->getRequestTime());
    return $ret;
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
      'PLAYLIST_GALLERY' => $this->t('Gallery')->__toString(),
      'PLAYLIST_360' => $this->t('360° Gallery')->__toString(),
      'URL' => $this->t('Url')->__toString(),
      'PAGELET' => $this->t('Pagelet')->__toString(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getContentRealType($content) {
    if ($content->contentType == 'PLAYLIST') {
      $is_gallery = FALSE;
      $is_360 = FALSE;
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
    }
    return $content->contentType;
  }

  /**
   * @param $properties
   *
   * @return array|mixed|null
   */
  public function contentFindByProperties($properties) {
    $cache_key = $this->gluey($properties);
    $cid = 'contentFindByProperties_' . md5($cache_key);
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $currentLanguage = $this->languageManager->getCurrentLanguage();

      // Call the API endpoint.
      $data = Thronintegration_Api::contentsFindByProperties(
        $this->config->get('client_id'),
        $login_data['token'],
        $login_data['rootCategoryId'],
        isset($properties['limit']) && isset($properties['page']) ? $properties['limit'] * ($properties['page'] - 1) : 0,
        isset($properties['limit']) ? $properties['limit'] : NULL,
        !empty($properties['contentType']) ? $properties['contentType'] : FALSE,
        isset($properties['divArea']) ? $properties['divArea'] : FALSE,
        isset($properties['tags']) ? $properties['tags'] : FALSE,
        isset($properties['keyword']) ? $properties['keyword'] : FALSE,
        isset($properties['orderBy']) ? $properties['orderBy'] : FALSE,
        TRUE,
        isset($properties['langcode']) ? $properties['langcode'] : $currentLanguage->getId()
      );
      if ($data['resultCode'] !== 'OK') {
        throw new \Exception($data['errorDescription']);
      }
      $this->cache->set($cid, $data, $this->time->getRequestTime() + $this->getCacheInterval());
      return $data;
    }
    catch (AppTokenExpiredException $ex) {
      return $this->refreshAndRecall('contentFindByProperties', NULL, $ex, [$properties]);
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
   * Get's the information about media.
   *
   * @return array|FALSE
   * @throws \Exception
   */
  public function getMediaDetails($content_id, $key = NULL) {
    $cid = 'mediaContentDetails_' . md5($content_id);
    if ($key) { $cid .= ':' . $key; }
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      if (!$login_data = $this->getLoginData()) {
        throw new \Exception('LoginApp error');
      }

      $data = Thronintegration_Api::getMediaContentDetails($this->config->get('client_id'), $login_data['token'], $content_id);
      if ($data && isset($data['status']) && $data['status'] == "OK") {
        $ret = [];
        if ($key && isset($data['mediaContent'][$key])) {
          $ret = $data['mediaContent'][$key];
        }
        else {
          $ret = $data['mediaContent'];
        }
        $this->cache->set($cid, $ret, $this->time->getRequestTime() + $this->getCacheInterval());
        return $ret;
      }
      return FALSE;
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
   * Get's the Interval in secconds for the given cache expire amount.
   *
   * @param string|NULL $name
   *   The interval name to retreive.
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
}