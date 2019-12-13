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
   * Statically cached API response for a given asset.
   *
   * @var array
   */
  protected $apiResponse;

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
  public function getMetadata(MediaInterface $media, $name = NULL, $langcode = NULL) {
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
      $this->apiResponse = $this->THRONApi->getContentDetail($xcontentId);
      if (!$this->apiResponse) {
        return FALSE;
      }
    }

    switch ($name) {
      case 'thumbnail_uri':
        if (!empty($this->apiResponse->content->dynThumbService)) {
          if ($file = system_retrieve_file($this->apiResponse->content->dynThumbService, NULL, TRUE)) {
            return $file->getFileUri();
          }
        }
        return parent::getMetadata($media, 'thumbnail_uri');

      case 'created':
        return isset($this->apiResponse->content->creationDate) ? $this->apiResponse->content->creationDate : FALSE;

      case 'modified':
        return isset($this->apiResponse->content->lastUpdate) ? $this->apiResponse->content->lastUpdate : FALSE;

      case 'default_name':
        return isset($this->apiResponse->content->locales[0]->name) ? $this->apiResponse->content->locales[0]->name : parent::getMetadata($media, 'default_name');

      default:
        if (isset($this->apiResponse->content->{$name})) {
          return $this->apiResponse->content->{$name};
        }
    }

    $metadata = $this->buildMetadata($this->apiResponse->content, $langcode);

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
   * @param string $langcode
   *
   * @return mixed
   */
  public function buildMetadata($data, $langcode) {
    $metadata = [];
    if (!empty($data)) {
      $metadata['id'] = $data->id;
      $metadata['contentType'] = $data->contentType;
      $metadata['contentTypeFull'] = $this->THRONApi->getContentRealType($data);
      $metadata['owner'] = $data->owner;
      $metadata['created'] = isset($data->creationDate) ? $data->creationDate : FALSE;
      $metadata['modified'] = isset($data->lastUpdate) ? $data->lastUpdate : FALSE;
      $metadata['default_name'] = isset($data->locales[0]->name) ? $data->locales[0]->name : '';
      $metadata['default_description'] = isset($data->locales[0]->description) ? $data->locales[0]->description : '';
      $metadata['default_pretty_name'] = isset($data->prettyIds[0]->id) ? $data->prettyIds[0]->id : Html::cleanCssIdentifier($metadata['default_name']);
      // Locale data.
      $metadata['locales'] = json_decode(json_encode($data->locales), TRUE);
      foreach ($metadata['locales'] as $locale_data) {
        if ($locale_data['locale'] == $langcode) {
          $metadata['name'] = $locale_data['name'];
          $metadata['description'] = isset($locale_data['description']) ? $locale_data['description'] : '';
        }
      }
      $metadata['prettyIds'] = json_decode(json_encode($data->prettyIds), TRUE);
      foreach ($metadata['prettyIds'] as $prettyId) {
        if ($prettyId['locale'] == $langcode) {
          $metadata['pretty_name'] = $prettyId['id'];
        }
      }
      $tags = $this->buildTagsData($data->itags, $langcode);
      $metadata['tags'] = array_column($tags, 'name');

      $clientId = $this->config->get("client_id");
      $login_data = $this->THRONApi->getLoginData();
      $pkey = $login_data['pkey'];

      // Image specific data.
      if ($metadata['contentType'] == 'IMAGE') {
        $metadata['width']  = $data->deliverySize->maxWidth;
        $metadata['height'] = $data->deliverySize->maxHeight;
        $metadata['aspect_ratio'] = $data->deliverySize->aspectRatio;

        $metadata['thumbnail_url'] = "//$clientId-cdn.thron.com/delivery/public/thumbnail/$clientId/{$data->id}/$pkey/std/320x0/";
        $metadata['thumbnail_url'] .= isset($metadata['pretty_name']) ? $metadata['pretty_name'] : $metadata['default_pretty_name'];

        $divArea = join('x', [
          $metadata['width'],
          $metadata['height'],
        ]);
        $metadata['content_url'] = "//$clientId-cdn.thron.com/delivery/public/image/$clientId/{$data->id}/$pkey/std/$divArea/";
        $metadata['content_url'] .= isset($metadata['pretty_name']) ?
          $metadata['pretty_name'] :
          (isset($metadata['default_pretty_name']) && $metadata['default_pretty_name'] != '_' ? $metadata['default_pretty_name'] : $data->id);
        $metadata['content_url_pattern'] = "//$clientId-cdn.thron.com/delivery/public/image/$clientId/{$data->id}/$pkey/std/@divArea/";
        $metadata['content_url_pattern'] .= isset($metadata['pretty_name']) ? $metadata['pretty_name'] : $metadata['default_pretty_name'];

        $responsiveness = $this->config->get('responsive_pictures_breakpoints');
        if(empty($responsiveness))
          $responsiveness=$this->THRONApi->getBreakpointTags(true);

        if (!empty($responsiveness)) {
          $master_image_set_tag_id = $responsiveness['default'];

          $itags = array_filter($data->itags, function($itag) use ($master_image_set_tag_id) {
            return $itag->id == $master_image_set_tag_id;
          });
          $is_media_imgset_master_tagged = count($itags) === 1;

          if ($is_media_imgset_master_tagged && !empty($data->linkedContents)) {
            $imageset = [];
            foreach ($data->linkedContents as $content) {
              if ($apiResponse = $this->THRONApi->getContentDetail($content->id)) {
                $info = $apiResponse->content;
                if ($info->contentType == 'IMAGE' && !empty($info->itags)) {
                  $responsiveTag=false;
                  foreach($info->itags as $t) {
                    if(isset($responsiveness[$t->id]) && isset($responsiveness[$t->id]['name']) && trim($responsiveness[$t->id]['name']) != "") {
                      $responsiveTag = $responsiveness[$t->id]['name'];
                      break;
                    }
                  }
                  
                  if($responsiveTag) {
                    $tag_pretty_id = $responsiveTag;
                    $divArea = join('x', [
                      $info->deliverySize->maxWidth,
                      $info->deliverySize->maxHeight,
                    ]);
                   
                    $content_url = "//$clientId-cdn.thron.com/delivery/public/image/$clientId/{$info->id}/$pkey/std/$divArea/";
                    if (property_exists($info, 'prettyIds') && !empty($info->prettyIds)) {
                      $content_url .= $info->prettyIds[0]->id;
                    }
                    else {
                      $content_url .= $info->id;
                    }

                    $extension = array_filter($info->metadatas, function($item) {
                      return $item->name == '_SOURCE_MIMETYPE_';
                    });
                    $extension = reset($extension);
                    list(, $ext) = explode("/", $extension->value);
                    $content_url .= '.' .$ext;

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
        $metadata['aspect_ratio'] = $data->deliverySize->aspectRatio;

        $mimetype = NULL;
        foreach ($data->metadatas as $meta) {
          if ($meta->name === "_SOURCE_MIMETYPE_") {
            $mimetype = $meta->value;
            break;
          }
        }

        $sources = [];
        foreach ($data->deliveryInfo as $deliveryInfo) {
          if (strpos($deliveryInfo->channelType, 'WEB') !== FALSE) {
            $sources[$deliveryInfo->channelType] = [
              'poster' => $deliveryInfo->defaultThumbUrl,
              'src' => $deliveryInfo->contentUrl,
              'mime' => $mimetype,
            ];

            foreach ($deliveryInfo->sysMetadata as $meta) {
              $sources[$deliveryInfo->channelType][strtolower($meta->name)] = $meta->value;
            }
          }
        }
        $metadata['sources'] = $sources;

        $metadata['thumbnail_url'] = "//$clientId-cdn.thron.com/delivery/public/thumbnail/$clientId/{$data->id}/$pkey/std/320x0/";
        $metadata['thumbnail_url'] .= isset($metadata['pretty_name']) ? $metadata['pretty_name'] : $metadata['default_pretty_name'];

        $metadata['content_url'] = "//$clientId-cdn.thron.com/delivery/public/video/$clientId/{$data->id}/$pkey/WEBHD/";
        $metadata['content_url'] .= isset($metadata['pretty_name']) ? $metadata['pretty_name'] : $metadata['default_pretty_name'];
      }

      else {
        $metadata['aspect_ratio'] = $data->deliverySize->aspectRatio;
        $metadata['thumbnail_url'] = $data->dynThumbService;
      }
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
    $tags = $this->THRONApi->filterTagsDefinitions($itags, $langcode);

    foreach ($tags as $id => $tag) {
      if (!empty($tag['name'])) {
        continue;
      }

      $definition = $this->THRONApi->getTagDefinitionDetail($tag);
      if (!$definition) {
        continue;
      }

      $name = $this->THRONApi->getSingleLocaleData($definition['names'], $langcode);
      $tags[$id]['name'] = isset($name['label']) ? $name['label'] : '';
    }

    return $tags;
  }

  public function setIsImageSource($bool = FALSE) {
    $this->isImageSource = $bool;
  }

  public function isImageSet() {
    return $this->isImageSource ?: FALSE;
  }
}
