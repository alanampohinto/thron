<?php

namespace Drupal\thron\Plugin\media\Source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\thron\Exception\ThronException;
use Drupal\thron\THRONApiInterface;
use Drupal\thron\Utils\THRONApiUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\thron\Integration\Thronintegration_Utils;

/**
 * Provides media source plugin for THRON.
 *
 * @MediaSource(
 *   id = "thron",
 *   label = @Translation("Thron Media"),
 *   description = @Translation("Provides business logic and metadata for THRON."),
 *   default_thumbnail_filename = "thron_no_image.png",
 *   allowed_field_types = {"string", "string_long"}
 * )
 */
class ThronMediaSource extends MediaSourceBase {

  /**
   * Account proxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $accountProxy;

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Statically cached API response for a given asset (for content/search invocations).
   *
   * @var array
   */
  protected $apiResponse;

  /**
   * Statically cached API response for a given asset (for delivery/contentDetail invocations).
   *
   * @var array
   */
  protected $apiResponseContentDetail;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * @var \Drupal\thron\THRONApiInterface
   */
  protected $THRONApi;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Flag to say if this Source is to treat as image set.
   *
   * @var boolean
   */
  protected $isImageSource;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager service.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account_proxy
   *   Account proxy.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\thron\THRONApiInterface $thron_api
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, AccountProxyInterface $account_proxy, UrlGeneratorInterface $url_generator, LoggerChannelFactoryInterface $logger_factory, THRONApiInterface $thron_api) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);

    $this->accountProxy = $account_proxy;
    $this->urlGenerator = $url_generator;
    $this->logger = $logger_factory->get('thron');
    $this->config = $config_factory->get('thron.settings');
	$this->THRONApi = $thron_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('url_generator'),
      $container->get('logger.factory'),
      $container->get('thron_api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      'id' => $this->t('ID'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'source_field' => 'field_thron_id',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name = NULL, $langcode = NULL, $divArea = NULL) {
    if (!$source_field = $this->configuration['source_field']) {
      return FALSE;
    }

    if (!$xcontentId = $media->{$source_field}->value) {
      return FALSE;
    }

    if ($name == 'id') {
      return $xcontentId;
    }

    if (!isset($this->apiResponse)) {
      $apiResponse = $this->THRONApi->getMediaDetails($xcontentId, NULL, $divArea);
      if (!$apiResponse)
        return FALSE;
      else
        $this->apiResponse = $apiResponse[0];
    }

    if(!isset($this->apiResponseContentDetail)) {
      $this->apiResponseContentDetail = $this->THRONApi->getContentDetail($xcontentId, $divArea);
    }

    switch ($name) {
      case 'thumbnail_uri':
        if ($file = system_retrieve_file($this->apiResponseContentDetail->dynThumbService, NULL, TRUE)) {
          return $file->getFileUri();
        }
        return parent::getMetadata($media, 'thumbnail_uri');

      case 'created':
        return isset($this->apiResponse["creationDate"]) ? $this->apiResponse["creationDate"] : FALSE;

      case 'modified':
        return isset($this->apiResponse["details"]["lastUpdate"]) ? $this->apiResponse["details"]["lastUpdate"] : FALSE;

      case 'default_name':
        return isset($this->apiResponse["details"]["locales"][0]["name"]) ? $this->apiResponse["details"]["locales"][0]["name"] : parent::getMetadata($media, 'default_name');

      default:
        if(trim($name) != "" && isset($this->apiResponse["details"][$name])) {
          return $this->apiResponse["details"][$name];
        }
    }

    $metadata = $this->buildMetadata($this->apiResponse, $this->apiResponseContentDetail, $langcode);

    if (!empty($metadata) && empty($name)) {
      return $metadata;
    }
    elseif (isset($metadata[$name])) {
      return $metadata[$name];
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Check the connection with thron.
    try {
      $this->THRONApi->loginApp();
    }
    catch (ThronException $ex) {
      $ex->displayMessage();
      $ex->logException();
      return FALSE;
    }

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * Returns the metadata assoc array for given data structure.
   *
   * @param mixed $data
   * @param mixed $contentDetail
   * @param string $langcode
   *
   * @return mixed
   */
  public function buildMetadata($data, $contentDetail, $langcode) {
    $metadata = [];
    if (!empty($data)) {
      $metadata['id'] = $data["id"];
      $metadata['contentType'] = $data["contentType"];
      $metadata['contentTypeFull'] = $this->THRONApi->getContentRealType($data);
      $metadata['extension'] = $data["details"]["source"]["extension"];
      $metadata['channels'] = $data["details"]["availableChannels"];
      $metadata['owner'] = $data["details"]["owner"]["ownerFullName"];
      $metadata['created'] = $this->THRONApi->getValueOrDefault($data["creationDate"], FALSE);
      $metadata['modified'] = $this->THRONApi->getValueOrDefault($data["details"]["lastUpdate"], FALSE);
      $metadata['default_name'] = $this->THRONApi->getValueOrDefault($data["details"]["locales"][0]["name"], '');
      if(count($data["details"]["locales"])>0 && isset($data["details"]["locales"][0]["description"]) && !empty($data["details"]["locales"][0]["description"]))
        $metadata['default_description'] = $data["details"]["locales"][0]["description"];
      else
        $metadata['default_description'] = '';

      if(count($data["details"]["prettyIds"])>0 && isset($data["details"]["prettyIds"][0]["id"]) && !empty($data["details"]["prettyIds"][0]["id"]))
        $metadata['default_pretty_name'] = $data["details"]["prettyIds"][0]["id"];
      else
        $metadata['default_pretty_name'] = Html::cleanCssIdentifier($metadata['default_name']);

      // Locale data.
      $metadata['locales'] = json_decode(json_encode($data["details"]["locales"]), TRUE);
      foreach ($metadata['locales'] as $locale_data) {
        if ($locale_data['locale'] == $langcode) {
          $metadata['name'] = $locale_data['name'];
          $metadata['description'] = isset($locale_data['description']) ? $locale_data['description'] : '';
        }
      }
      $metadata['prettyIds'] = json_decode(json_encode($data["details"]["prettyIds"]), TRUE);
      foreach ($metadata['prettyIds'] as $prettyId) {
        if ($prettyId['locale'] == $langcode) {
          $metadata['pretty_name'] = $prettyId['id'];
        }
      }
      $tags = $this->buildTagsData($data["details"]["itags"], $langcode);
      $metadata['tags'] = [];
      foreach(array_values($tags) as $t)
        if(trim($t["name"]) != "")
          array_push($metadata['tags'], $t["name"]);

      $clientId = $this->config->get("client_id");
      $login_data = $this->THRONApi->getLoginData();
      $pkey = $login_data['pkey'];

      // Image specific data.
      if ($metadata['contentType'] == 'IMAGE') {
        $metadata['width']  = $contentDetail->deliverySize->maxWidth;
        $metadata['height'] = $contentDetail->deliverySize->maxHeight;
        $metadata['aspect_ratio'] = $contentDetail->deliverySize->aspectRatio;

        $metadata['thumbnail_url'] = "//$clientId-cdn.thron.com/delivery/public/thumbnail/$clientId/{$data["id"]}/$pkey/std/0x0/";
        $metadata['thumbnail_url'] .= isset($metadata['pretty_name']) ? $metadata['pretty_name'] : $metadata['default_pretty_name'];

        $divArea = implode('x', [
          $metadata['width'],
          $metadata['height'],
        ]);
        $metadata['content_url'] = "//$clientId-cdn.thron.com/delivery/public/image/$clientId/{$data["id"]}/$pkey/std/$divArea/";
        $metadata['content_url'] .= isset($metadata['pretty_name']) ?
          $metadata['pretty_name'] :
          (isset($metadata['default_pretty_name']) && $metadata['default_pretty_name'] != '_' ? $metadata['default_pretty_name'] : $data["id"]);
        $metadata['content_url_pattern'] = "//$clientId-cdn.thron.com/delivery/public/image/$clientId/{$data["id"]}/$pkey/std/@divArea/";
        $metadata['content_url_pattern'] .= isset($metadata['pretty_name']) ? $metadata['pretty_name'] : $metadata['default_pretty_name'];

        $responsiveness = $this->config->get('responsive_pictures_breakpoints');
        if(empty($responsiveness))
          $responsiveness=$this->THRONApi->getBreakpointTags(TRUE);

        if (!empty($responsiveness)) {
          $master_image_set_tag_id = $responsiveness['default'];

          $itags = array_filter($data["details"]["itags"], function($itag) use ($master_image_set_tag_id) {
            return $itag["id"] == $master_image_set_tag_id;
          });
          $is_media_imgset_master_tagged = count($itags) === 1;

          if ($is_media_imgset_master_tagged && !empty($data["details"]["linkedContent"])) {
            $imageset = [];
            foreach ($data["details"]["linkedContent"] as $content) {
              if ($apiResponse = $this->THRONApi->getContentDetail($content["id"])) {
                $info = $apiResponse["details"];
                if ($info["contentType"] == 'IMAGE' && !empty($info["itags"])) {
                  $responsiveTag=FALSE;
                  foreach($info["itags"] as $t) {
                    if(isset($responsiveness[$t["id"]]) && isset($responsiveness[$t["id"]]['name']) && trim($responsiveness[$t["id"]]['name']) != "") {
                      $responsiveTag = $responsiveness[$t["id"]]['name'];
                      break;
                    }
                  }
                  
                  if($responsiveTag) {
                    $tag_pretty_id = $responsiveTag;
                    $divArea = implode('x', [
                      $contentDetail->deliverySize->maxWidth,
                      $contentDetail->deliverySize->maxHeight,
                    ]);
                   
                    $content_url = "//$clientId-cdn.thron.com/delivery/public/image/$clientId/{$info["id"]}/$pkey/std/$divArea/";
                    if (isset($info['prettyIds']) && !count($info["prettyIds"])>0) {
                      $content_url .= $info["prettyIds"][0]["id"];
                    }
                    else {
                      $content_url .= $info["id"];
                    }

                    $content_url .= '.' .$info["source"]["extension"];

                    $imageset[$tag_pretty_id] = $content_url;
                  }
                }
              }
            }
            if (!empty($imageset)) {
              $metadata['imageset'] = $imageset;
            }
          }
        }

      }

      // Video specific data.
      elseif ($metadata['contentType'] == 'VIDEO') {
        $metadata['aspect_ratio'] = $contentDetail->deliverySize->aspectRatio;
        $mimetype = Thronintegration_Utils::getExtensionFromMimeType($data["details"]["source"]["extension"], TRUE);
        $sources = [];
        $filteredChannels = $this->getchannels($contentDetail->deliveryInfo);
        $channelsList = [];
        foreach ($filteredChannels as $channelName => $fc) {
          if(count($fc)>0)
            array_push($channelsList, $fc[0]);
        }

        foreach ($channelsList as $deliveryInfo) {
          $sources[$deliveryInfo->channelType] = [
            'poster' => $deliveryInfo->defaultThumbUrl,
            'src' => "//$clientId-cdn.thron.com/delivery/public/video/$clientId/{$data["id"]}/$pkey/$deliveryInfo->channelType/".(isset($metadata['pretty_name']) ? $metadata['pretty_name'] : $metadata['default_pretty_name']),
            'mime' => $mimetype,
          ];

          foreach ($deliveryInfo->sysMetadata as $meta) {
            $sources[$deliveryInfo->channelType][strtolower($meta->name)] = $meta->value;
          }
        }
        $metadata['sources'] = $sources;

        $metadata['thumbnail_url'] = "//$clientId-cdn.thron.com/delivery/public/thumbnail/$clientId/{$data["id"]}/$pkey/std/1920x0/";
        $metadata['thumbnail_url'] .= isset($metadata['pretty_name']) ? $metadata['pretty_name'] : $metadata['default_pretty_name'];

        $metadata['content_url'] = "//$clientId-cdn.thron.com/delivery/public/video/$clientId/{$data["id"]}/$pkey/WEBHD/";
        $metadata['content_url'] .= isset($metadata['pretty_name']) ? $metadata['pretty_name'] : $metadata['default_pretty_name'];
      }
      else {
        $metadata['aspect_ratio'] = $contentDetail->deliverySize->aspectRatio;
        $metadata['thumbnail_url'] = $contentDetail->dynThumbService;
      }

      $metadata['thumbnail_url_pattern'] = "//$clientId-cdn.thron.com/delivery/public/thumbnail/$clientId/{$data["id"]}/$pkey/std/@divArea/";
      $metadata['thumbnail_url_pattern'] .= isset($metadata['pretty_name']) ? $metadata['pretty_name'] : $metadata['default_pretty_name'];
    }
    return $metadata;
  }

  /**
   * @param $itags
   * @param $langcode
   *
   * @return array
   */
  public function buildTagsData($itags, $langcode) {
    // get the tags definitions for the requested tags
    $tagsForClassifications=[];
    foreach($itags as $t) {
      if(!isset($tagsForClassifications[$t["classificationId"]]))
        $tagsForClassifications[$t["classificationId"]] = [];
      array_push($tagsForClassifications[$t["classificationId"]], $t["id"]);
    }

    $tags = [];
    foreach($tagsForClassifications as $classification_id => $tag_ids) {
      $retrieved_tags = $this->THRONApi->getTagsListByClassification($classification_id, $tag_ids, FALSE, FALSE);
      foreach ($retrieved_tags as $key => $tag) {
        $tags[$tag["id"]] = ["name" => $tag["name"], "classificationId" => $key];
      }
    }

    return $tags;
  }

  public function setIsImageSource($bool = FALSE) {
    $this->isImageSource = $bool;
  }

  public function isImageSet() {
    return $this->isImageSource ?: FALSE;
  }

  public function getchannels($deliveryInfo) {
    // Return the list of channels to be passed on to the player
    $channels = [];
    $channelTypesRegexps = [[
      "channel" => "STREAMHTTPIOSHD",
      "find" => "/^STREAMHTTPIOSHD.?/",
      "findNot" => FALSE
    ], [
      "channel" => "STREAMHTTPIOS",
      "find" => "/^STREAMHTTPIOS.?/",
      "findNot" => "/^STREAMHTTPIOSHD.?/",
    ], [
      "channel" => "WEBFULLHD",
      "find" => "/^WEBFULLHD.?/",
      "findNot" => FALSE
    ], [
      "channel" => "WEBHD",
      "find" => "/^WEBHD.?/",
      "findNot" => FALSE
    ], [
      "channel" => "WEB",
      "find" => "/^WEB.?/",
      "findNot" => "/^WEB(FULLHD|HD|AUDIO).?/"
    ]];
    
    foreach($channelTypesRegexps as $ctre) {
      foreach($deliveryInfo as $channel) {
        $shouldAdd = FALSE;
        preg_match($ctre["find"], $channel->channelType, $found);
        if(count($found)>0) {
          if(!$ctre["findNot"]) {
            $shouldAdd = TRUE;
          } else {
            preg_match($ctre["findNot"], $channel->channelType, $found);
            if(!$found) {
              $shouldAdd = TRUE;
            }
          }
        }

        if($shouldAdd) {
          if(!isset($channels[$ctre["channel"]]))
            $channels[$ctre["channel"]]=[];
  
          array_push($channels[$ctre["channel"]], $channel);
        }
      }
    }

    return $channels;
  }
}
