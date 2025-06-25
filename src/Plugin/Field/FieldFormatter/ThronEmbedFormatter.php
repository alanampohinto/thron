<?php

namespace Drupal\thron\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Template\Attribute;
use Drupal\thron\Integration\Thronintegration_Utils;
use Drupal\thron\Plugin\media\Source\ThronMediaSource;

/**
 * Plugin implementation of the 'THRON Embedded' formatter.
 *
 * @FieldFormatter(
 *   id = "thron_embedded",
 *   label = @Translation("THRON Player"),
 *   field_types = {"entity_reference"},
 *   weight = 1
 * )
 */
class ThronEmbedFormatter extends ThronFormatterBase
{

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
    return [
        'embed_template' => 'default',
        'embed_resizing' => 'fixed',
        'embed_resizing_fixed_width' => NULL,
        'embed_resizing_fixed_height' => NULL,
        'embed_resizing_fixed_link' => 1,
        'embed_resizing_responsive_width' => 100,
        'embed_advanced_option' => NULL,

      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $elements = parent::settingsForm($form, $form_state);

    /** @var \Drupal\media\Entity\Media $entity */
    $media = $form_state->get('entity');
  
    $settings = $this->fieldDefinition->getSettings();
    $target_type = $settings['target_type'] ?? NULL;

    if ($target_type === 'media') {
      $handler_settings = $settings['handler_settings'] ?? [];

      if (!empty($handler_settings['target_bundles'])) {
        $allowed_bundles = $handler_settings['target_bundles'];

        $media_storage = \Drupal::entityTypeManager()->getStorage('media');

        $media_entities = $media_storage->loadByProperties(['bundle' => reset($allowed_bundles)]);
        $media = reset($media_entities);

        if ($media && $media instanceof \Drupal\media\Entity\Media) {
          $source_plugin = $media->getSource(); 

          if ($source_plugin instanceof \Drupal\thron\Plugin\media\Source\ThronMediaSource) {
            // Retrieve THRON content Metadata.
            if ($metadata = $source_plugin->getMetadata($media, NULL, $this->THRON->getPreviewLanguage())) {
              $form_state->set('metadata', $metadata);
              $form_state->set('formatter', 'thron_embedded');

              $elements['#attached']['library'][] = 'thron/formatter_resizing_config';

              $default_width = 500;
              $default_height = 350;

              $ar_label = $metadata['aspect_ratio'] ?: $default_width . ':' . $default_height;
              if ($metadata['contentType'] == 'IMAGE') {
                $ar = $metadata['width'] / $metadata['height'];
                $default_width = $metadata['width'];
                $default_height = $metadata['height'];
              } elseif ($metadata['contentType'] == 'VIDEO') {
                list($w, $h) = explode(":", $metadata['aspect_ratio']);
                $ar = $w / $h;
                $default_height = ceil($default_width / $ar);
              } else {
                list($w, $h) = explode(":", $metadata['aspect_ratio']);
                $ar = ($w / $h);
                if ($w >= $default_width) {
                  $w = $default_width;
                }
                $h = $w / $ar;

                $default_height = ceil($default_width / $ar);
              }

              $elements['#attached']['drupalSettings']['thron_embed_form'] = [
                'aspectRatio' => $ar,
              ];

              $options = [];
              $data = $this->THRON->getVideoPlayerTemplatesList();
              if (!empty($data)) {
                $templates = $data['templates'];
                $defaultAdded = FALSE;

                $options = [];

                if (isset($data['default_templates']['default'])) {
                  $options[$data['default_templates']['default']] = $this->t('Default');
                  $defaultAdded = TRUE;
                }

                if (isset($data['default_templates']['noSkin'])) {
                  $options[$data['default_templates']['noSkin']] = $this->t('noSkin');
                  $defaultAdded = TRUE;
                }

                $sepAdded = FALSE;
                foreach ($templates as $template) {
                  if (
                    (isset($data['default_templates']['default']) && $template['id'] != $data['default_templates']['default']) &&
                    (isset($data['default_templates']['noSkin']) && $template['id'] != $data['default_templates']['noSkin'])
                  ) {
                    if (isset($options[$template['id']])) {
                      if ($defaultAdded && !$sepAdded) {
                        $options['---------'] = []; // separator
                        $sepAdded = TRUE;
                      }

                      $options[$template['id']] .= '  (' . $template['name'] . ')';
                    } else {
                      if ($defaultAdded && !$sepAdded) {
                        $options['---------'] = []; // separator
                        $sepAdded = TRUE;
                      }
                      $options[$template['id']] = $template['name'];
                    }
                  } else {
                    $options[$template['id']] = $template['name'];
                  }
                }
              }

              $elements['embed_template'] = [
                '#type' => 'select',
                '#title' => $this->t('Player Template'),
                '#description' => $this->t('Choose the template to be applied onto the player'),
                '#default_value' => $this->getSetting('embed_template') ?: $data['default_player_templates']['default'],
                '#options' => $options,
              ];

              // Change default values accordingly to previous selected settings.
              if ($user_defined_template = $this->privateTempStore->get('embed_template')) {
                $elements['embed_template']['#default_value'] = $user_defined_template;
              }

              $elements['embed_resizing'] = [
                '#type' => 'select',
                '#title' => $this->t('Resizing'),
                '#required' => TRUE,
                '#default_value' => $this->getSetting('embed_resizing'),
                '#options' => [
                  'fixed' => $this->t('Fixed (with aspect/ratio)'),
                  'responsive' => $this->t('Responsive'),
                ],
              ];

              $elements['embed_resizing_fixed_width'] = [
                '#type' => 'number',
                '#title' => $this->t('Width'),
                '#size' => 10,
                '#min' => 0,
                '#default_value' => $this->getSetting('embed_resizing_fixed_width') ?: $default_width,
                '#states' => [
                  'visible' => [
                    'select[name="attributes[data-entity-embed-display-settings][embed_resizing]"]' => ['value' => 'fixed'],
                  ],
                ],
              ];

              $elements['embed_resizing_fixed_height'] = [
                '#type' => 'number',
                '#title' => $this->t('Height'),
                '#size' => 10,
                '#min' => 0,
                '#default_value' => $this->getSetting('embed_resizing_fixed_height') ?: $default_height,
                '#states' => [
                  'visible' => [
                    'select[name="attributes[data-entity-embed-display-settings][embed_resizing]"]' => ['value' => 'fixed'],
                  ],
                ],
              ];

              $elements['embed_resizing_fixed_link'] = [
                '#type' => 'checkbox',
                '#default_value' => $this->getSetting('embed_resizing_fixed_link') ?: NULL,
                '#states' => [
                  'visible' => [
                    'select[name="attributes[data-entity-embed-display-settings][embed_resizing]"]' => ['value' => 'fixed'],
                  ],
                ],
              ];

              $elements['embed_resizing_fixed_ar_info'] = [
                '#prefix' => '<small>',
                '#markup' => $this->t('Aspect Ratio - @ratio ≈ @value', [
                  '@ratio' => $ar_label,
                  '@value' => round($ar, 2),
                ]),
                '#suffix' => '</small>',
                '#states' => [
                  'visible' => [
                    'select[name="attributes[data-entity-embed-display-settings][embed_resizing]"]' => ['value' => 'fixed'],
                  ],
                ],
              ];

              // Change default values accordingly to previous selected settings.
              if ($fixed_data = $this->privateTempStore->get('embed_resizing_fixed')) {
                if ($fixed_data['width'] && $fixed_data['height']) {
                  $elements['embed_resizing_fixed_width']['#default_value'] = $fixed_data['width'];
                  $elements['embed_resizing_fixed_height']['#default_value'] = $fixed_data['height'];
                }

                $elements['embed_resizing_fixed_link']['#default_value'] = $fixed_data['link'];
              }

              $elements['embed_resizing_responsive_width'] = [
                '#type' => 'number',
                '#title' => $this->t('Width'),
                '#size' => 10,
                '#min' => 0,
                '#max' => 100,
                '#attributes' => array('aspect_ratio' => $ar_label),
                '#default_value' => $this->getSetting('embed_resizing_responsive_width'),
                '#states' => [
                  'visible' => [
                    'select[name="attributes[data-entity-embed-display-settings][embed_resizing]"]' => ['value' => 'responsive'],
                  ],
                ],
              ];

              $elements['embed_resizing_responsive_height'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Height'),
                '#default_value' => 'auto',
                '#disabled' => TRUE,
                '#size' => 10,
                '#states' => [
                  'visible' => [
                    'select[name="attributes[data-entity-embed-display-settings][embed_resizing]"]' => ['value' => 'responsive'],
                  ],
                ],
              ];


              // Change default values accordingly to previous selected settings.
              if ($responsive_data = $this->privateTempStore->get('embed_resizing_responsive')) {
                if ($responsive_data['width']) {
                  $elements['embed_resizing_responsive_width']['#default_value'] = $responsive_data['width'];
                }
              }
              if ($metadata['contentType'] == 'IMAGE') {
                $advancedSetting = $this->getSetting('embed_advanced_option');
                if (isset($advancedSetting)) {
                  $this->privateTempStore->set($media->id() . '-embed_crop', $advancedSetting);
                }
                $elements['embed_advanced_option']['advanced'] = [
                  '#type' => 'container',
                  '#attributes' => [
                    'id' => [
                      'advanced-settings',
                    ],
                  ],
                ];

                $elements['embed_advanced_option']['advanced']['crop_mode'] = [
                  '#type' => 'select',
                  '#title' => $this->t('Crop mode'),
                  '#default_value' => (isset($advancedSetting["advanced"]["crop_mode"])) ? $advancedSetting["advanced"]["crop_mode"] : 'no crop',
                  '#required' => FALSE,
                  '#options' => [
                    'no crop' => $this->t('No crop'),
                    'auto' => $this->t('Auto'),
                    'centered' => $this->t('Centered'),
                    'product' => $this->t('Product'),
                    'manual' => $this->t('Manual'),
                  ],
                  '#prefix' => '<div class="wrapper-advanced">'
                ];

                $elements['embed_advanced_option']['advanced']['button_manual'] = [
                  '#type' => 'button',
                  '#value' => $this->t('Crop'),
                  '#button_type' => 'primary',
                  '#attributes' => array('id' => 'crop-done'),
                  '#suffix' => '</div>'
                ];

                $elements['embed_advanced_option']['advanced']['crop_description'] = [
                  '#type' => 'markup',
                  '#markup' => '<div id="rtisg-crop-description"><p>'.
                    '<span class="no-cropping">'. $this->t('Click the "Crop" button to frame a specific area.') . '</span>'.
                    '<span class="cropping hidden">'. $this->t('Zoom and move the image to frame the area you want to crop.') . '</span>'.
                    '</p></div>',
                ];

                $elements['embed_advanced_option']['advanced']['player'] = [
                  '#type' => 'markup',
                  '#markup' => '<div id="rtisg"></div>',
                ];

                $elements['embed_advanced_option']['advanced']['player_params'] = [
                  '#type' => 'hidden',
                  '#default_value' => (isset($advancedSetting["advanced"]["player_params"])) ? $advancedSetting["advanced"]["player_params"] : '',
                ];

                $elements['embed_advanced_option']['advanced']['brightness'] = array(
                  '#type' => 'range',
                  '#title' => $this->t('Brightness'),
                  '#default_value' => (isset($advancedSetting["advanced"]["brightness"])) ? $advancedSetting["advanced"]["brightness"] : 100,
                  '#min' => 0,
                  '#max' => 200,
                  '#prefix' => '<div class="wrapper-range">'
                );
                $elements['embed_advanced_option']['advanced']['brightness_input'] = [
                  '#type' => 'textfield',
                  '#size' => 3,
                  '#default_value' => (isset($advancedSetting["advanced"]["brightness_input"])) ? $advancedSetting["advanced"]["brightness_input"] : 100,
                  '#suffix' => '</div>',
                  '#disabled' => TRUE
                ];

                $elements['embed_advanced_option']['advanced']['contrast'] = array(
                  '#type' => 'range',
                  '#title' => $this->t('Contrast'),
                  '#default_value' => (isset($advancedSetting["advanced"]["contrast"])) ? $advancedSetting["advanced"]["contrast"] : 100,
                  '#min' => 0,
                  '#max' => 200,
                  '#prefix' => '<div class="wrapper-range">'
                );

                $elements['embed_advanced_option']['advanced']['contrast_input'] = [
                  '#type' => 'textfield',
                  '#size' => 3,
                  '#default_value' => (isset($advancedSetting["advanced"]["contrast_input"])) ? $advancedSetting["advanced"]["contrast_input"] : 100,
                  '#suffix' => '</div>',
                  '#disabled' => TRUE
                ];

                $elements['embed_advanced_option']['advanced']['sharpness'] = array(
                  '#type' => 'range',
                  '#title' => $this->t('Sharpness'),
                  '#default_value' => (isset($advancedSetting["advanced"]["sharpness"])) ? $advancedSetting["advanced"]["sharpness"] : 100,
                  '#min' => 0,
                  '#max' => 200,
                  '#prefix' => '<div class="wrapper-range">'
                );

                $elements['embed_advanced_option']['advanced']['sharpness_input'] = [
                  '#type' => 'textfield',
                  '#size' => 3,
                  '#default_value' => (isset($advancedSetting["advanced"]["sharpness_input"])) ? $advancedSetting["advanced"]["sharpness_input"] : 100,
                  '#suffix' => '</div>',
                  '#disabled' => TRUE
                ];

                $elements['embed_advanced_option']['advanced']['color'] = array(
                  '#type' => 'range',
                  '#title' => $this->t('Color'),
                  '#default_value' => (isset($advancedSetting["advanced"]["color"])) ? $advancedSetting["advanced"]["color"] : 100,
                  '#min' => 0,
                  '#max' => 200,
                  '#prefix' => '<div class="wrapper-range">'
                );

                $elements['embed_advanced_option']['advanced']['color_input'] = [
                  '#type' => 'textfield',
                  '#size' => 3,
                  '#default_value' => (isset($advancedSetting["advanced"]["color_input"])) ? $advancedSetting["advanced"]["color_input"] : 100,
                  '#suffix' => '</div>',
                  '#disabled' => TRUE
                ];

                $elements['embed_advanced_option']['advanced']['quality'] = array(
                  '#type' => 'range',
                  '#title' => $this->t('Quality'),
                  '#default_value' => (isset($advancedSetting["advanced"]["quality"])) ? $advancedSetting["advanced"]["quality"] : 90,
                  '#prefix' => '<div class="wrapper-range">'
                );

                $elements['embed_advanced_option']['advanced']['quality_input'] = [
                  '#type' => 'textfield',
                  '#size' => 3,
                  '#default_value' => (isset($advancedSetting["advanced"]["quality_input"])) ? $advancedSetting["advanced"]["quality_input"] : 90,
                  '#suffix' => '</div>',
                  '#disabled' => TRUE
                ];
              }

              $login_data = $this->THRON->getLoginData();
              $elements['#attached']['library'][] = 'thron/crop';
              $elements['#attached']['drupalSettings']['thron']['crop'] = [
                'clientId' => $this->config->get('client_id'),
                'xcontentId' => $metadata['id'],
                'sessId' => $login_data['pkey'],
              ];
            } else {
              $elements['media_info_error'] = [
                '#type' => 'item',
                '#markup' => $this->t('Can\'t access the media info. Something\'s gone wrong'),
              ];
            }
          }
        }
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $field_type = $this->fieldDefinition->getType();
    $elements = [];

    /** @var \Drupal\Core\Field\Plugin\Field\FieldType\StringItem $item */
    foreach ($items as $delta => $item) {
      if ($field_type == 'entity_reference') {
        /** @var \Drupal\Core\Entity\Plugin\DataType\EntityReference $entity_reference */
        $entity_reference = $item->get('entity');
        /** @var \Drupal\media\Entity\Media $media */
        $media = $entity_reference->getValue();

        if (isset($media)) {
          /** @var \Drupal\media\MediaSourceInterface $source_plugin */
          $source_plugin = $media->getSource();
          if ($source_plugin instanceof ThronMediaSource) {
            $language = $this->THRON->getPreviewLanguage();
            // Retrieve THRON content Metadata.
            if ($metadata = $source_plugin->getMetadata($media, NULL, $language)) {
              $wrapper_attributes = [];
              $inner_attributes = [];
              $attached = [];

              $displaySettings = [];
              $uniqueDiv = Html::getUniqueId($metadata['id']);

              $formatter_settings = $this->getSettings();
              $this->privateTempStore->set('embed_template', $formatter_settings['embed_template']);

              $view_mode = $this->viewMode;
              $ckeditor_preview_mode = FALSE;

              //IF VIEW_MODE = _entity_embed
              if ($view_mode == '_entity_embed') {
                $route_match = \Drupal::routeMatch();
                if (strpos($route_match->getRouteName(), 'entity.node.') === 0) {
                  $view_mode = 'full';

                  $login_data = $this->THRON->getLoginData();

                  // is there already an embed code for this content?
                  $node = \Drupal::routeMatch()->getParameter('node');
                  if ($node) {
                    try {
                      $nid = $node->id();
                    } catch (\Exception $ex) {
                      $nid = FALSE;
                    }
                  } else $nid = FALSE;

                  if ($nid) {
                    $embedCodeId = $this->THRON->getThronMediaEmbedId($metadata['id'], $nid, $formatter_settings['embed_template']);
                    if (!$embedCodeId) {
                      // find the label for this template
                      $template_settings = $this->THRON->getVideoPlayerTemplateData($formatter_settings['embed_template']);
                      $templateLabel = "unknown";
                      if ($template_settings)
                        if (isset($template_settings["item"]["name"]))
                          $templateLabel = $template_settings["item"]["name"];
                        elseif (isset($template_settings["name"]))
                          $templateLabel = $template_settings["name"];
                      $context = $login_data['tracking_context'];
                      $embed_player_code = $this->THRON->insertPlayerEmbedCode($formatter_settings['embed_template'], $templateLabel, $context, $metadata['id'], $login_data['token']);
                      $displaySettings['embed_player_code'] = $embed_player_code['item'];
                      $embedCodeId = $embed_player_code['item']['id'];
                      $this->THRON->setThronMediaEmbedId($metadata['id'], $nid, $formatter_settings['embed_template'], $embedCodeId);
                    }
                  } else $embedCodeId = FALSE;

                  // get the folder pkey from the application's settings
                  $pkey = $login_data['pkey'];
                  $params = [];
                  $enhance = [];
                  $attached['library'][] = 'thron/formatter';
                  if (isset($formatter_settings["embed_advanced_option"])) {
                    if ($formatter_settings["embed_advanced_option"]["advanced"]["crop_mode"] == 'manual') {
                      $params = json_decode($formatter_settings["embed_advanced_option"]["advanced"]["player_params"]);
                    } else {
                      $params['scalemode'] = $formatter_settings["embed_advanced_option"]["advanced"]["crop_mode"];
                      $params['enhance'] = 'brightness:' . $formatter_settings["embed_advanced_option"]["advanced"]["brightness"];
                      $params['enhance'] .= ',contrast:' . $formatter_settings["embed_advanced_option"]["advanced"]["contrast"];
                      $params['enhance'] .= ',sharpness:' . $formatter_settings["embed_advanced_option"]["advanced"]["sharpness"];
                      $params['enhance'] .= ',color:' . $formatter_settings["embed_advanced_option"]["advanced"]["color"];
                      $params['quality'] = $formatter_settings["embed_advanced_option"]["advanced"]["quality"];
                    }
                  }
                  $attached['drupalSettings']['thron']['players'][$uniqueDiv] = [
                    'clientId' => $this->config->get('client_id'),
                    'xcontentId' => $metadata['id'],
                    'sessId' => $pkey,
                    'language' => $language,
                    'rtie' => $params,
                  ];
                  if ($nid && $embedCodeId) {
                    $attached['drupalSettings']['thron']['players'][$uniqueDiv]['embedCodeId'] = $embedCodeId;
                  }
                  $wrapper_attributes['class'] = ['player-wrap'];
                  $wrapper_attributes['style'] = 'position:relative;';
                  $inner_attributes['class'] = ['player-placeholder'];
                  $inner_attributes['style'] = 'position:absolute;width:100%;height:100%;top:0;';
                }
                //ckeditor preview
                else {
                  $ckeditor_preview_mode = TRUE;
                  $wrapper_attributes['class'] = ['teaser-content'];
                  $wrapper_attributes['style'] = 'position:relative;border:1px solid grey;overflow:hidden;';
                  $inner_attributes['class'] = ['thron-thumbnail'];
                  $inner_attributes['style'] = 'position:absolute;width:100%;height:auto;top:50%;transform:translateY(-50%);';
                }
              }

              //IF RESIZE FIXED
              if ($formatter_settings['embed_resizing'] == 'fixed') {
                $sizes = [
                  'width' => $formatter_settings['embed_resizing_fixed_width'],
                  'height' => $formatter_settings['embed_resizing_fixed_height'],
                  'link' => $formatter_settings['embed_resizing_fixed_link']
                ];
                $this->privateTempStore->set('embed_resizing_fixed', $sizes);

                $width = !empty($sizes['width']) && $sizes['width'] !== "0" ? $sizes['width'] : NULL;
                $height = !empty($sizes['height']) && $sizes['height'] !== "0" ? $sizes['height'] : NULL;

                if (empty($sizes['width']) || empty($sizes['height'])) {
                  list($w, $h) = explode(":", $metadata['aspect_ratio']);
                  $ar = $w / $h;
                  if (!$height) {
                    $height = $width / $ar;
                  }
                  if (!$width) {
                    $width = $height * $ar;
                  }
                }

                $wrapper_attributes['style'] .= new FormattableMarkup('width:@width;height:@height;', [
                  '@width' => $width . 'px',
                  '@height' => $height . 'px',
                ]);
              }
              //ELSE RELATIVE
              else {
                $this->privateTempStore->set('embed_resizing_responsive', [
                  'width' => $formatter_settings['embed_resizing_responsive_width'],
                ]);

                $default_width = 500;
                $default_height = 350;
                $ratio = $default_height / $default_width;
                if (isset($metadata['height']) && isset($metadata['width'])) {
                  $ratio = $metadata['height'] / $metadata['width'];
                } elseif (isset($metadata['aspect_ratio'])) {
                  list($w, $h) = explode(":", $metadata['aspect_ratio']);
                  $ratio = $h / $w;
                }

                $wrapper_attributes['style'] .= new FormattableMarkup('width:@width;padding-top:@paddingTop;', [
                  '@width' => $formatter_settings['embed_resizing_responsive_width'] . '%',
                  '@paddingTop' => ($ratio * $formatter_settings['embed_resizing_responsive_width']) . '%',
                ]);

                // Hack for CK editor to show a width-less element as wide as possible.
                if ($ckeditor_preview_mode) {
                  $base = 800;
                  $fake_width = $base * $formatter_settings['embed_resizing_responsive_width'] / 100;
                  $wrapper_attributes['style'] .= new FormattableMarkup('width:@width;padding-top:@paddingTop;', [
                    '@width' => $fake_width . 'px',
                    '@paddingTop' => ($ratio * $fake_width) . 'px',
                  ]);
                }
              }

              // Hack for CK editor to show edited preview by unique content_url
              if ($ckeditor_preview_mode) {

                //MEDIA IMAGE
                if ($metadata['contentType'] == 'IMAGE') {
                  $queryParams = '';
                  $this->privateTempStore->set('embed_image_as_webp', $formatter_settings['embed_image_as_webp']);
                  $ext = $metadata["extension"];
                  if ($ext == 'webp' && $formatter_settings['embed_image_as_webp']) {
                    $queryParams .= '&format=webp';
                  }

                  if ($formatter_settings['embed_imageset']) {
                    $metadata['use_picture'] = TRUE;
                    $metadata['content_url'] .= '.' . $ext;
                    $responsiveness = $this->config->get('responsive_pictures_breakpoints');
                    if (empty($responsiveness))
                      $responsiveness = $this->THRON->getBreakpointTags(TRUE);

                    $new_imageset = [];
                    if (!empty($metadata['imageset']) && $responsiveness) {
                      foreach ($metadata['imageset'] as $media_key => $url) {
                        $new_imageset[$media_key] = [
                          'srcset' => $url,
                          'media' => $this->getImageSetValueByMediaName($responsiveness, $media_key),
                          'type' => Thronintegration_Utils::getExtensionFromMimeType($ext, TRUE),
                        ];
                      }
                    }
                    $metadata['imageset'] = $new_imageset;
                  }

                  if (isset($formatter_settings["embed_advanced_option"])) {
                    if ($formatter_settings["embed_advanced_option"]["advanced"]["crop_mode"] == 'manual') {
                      $params = json_decode($formatter_settings["embed_advanced_option"]["advanced"]["player_params"], true);
                    } else {
                      $params['scalemode'] = $formatter_settings["embed_advanced_option"]["advanced"]["crop_mode"];
                      $params['enhance'] = 'brightness:' . $formatter_settings["embed_advanced_option"]["advanced"]["brightness"];
                      $params['enhance'] .= ',contrast:' . $formatter_settings["embed_advanced_option"]["advanced"]["contrast"];
                      $params['enhance'] .= ',sharpness:' . $formatter_settings["embed_advanced_option"]["advanced"]["sharpness"];
                      $params['enhance'] .= ',color:' . $formatter_settings["embed_advanced_option"]["advanced"]["color"];
                      $params['quality'] = $formatter_settings["embed_advanced_option"]["advanced"]["quality"];
                    }
                    $queryParams .= (!empty($params['scalemode'])) ? '&scalemode=' . $params['scalemode'] : '';
                    $queryParams .= (!empty($params['scalemode']) && $params['scalemode'] == 'manual') ? '&cropmode=pixel' : '';
                    $queryParams .= (!empty($params['cropx'])) ? '&cropx=' . $params['cropx'] : '';
                    $queryParams .= (!empty($params['cropy'])) ? '&cropy=' . $params['cropy'] : '';
                    $queryParams .= (!empty($params['cropw'])) ? '&cropw=' . $params['cropw'] : '';
                    $queryParams .= (!empty($params['croph'])) ? '&croph=' . $params['croph'] : '';
                    $queryParams .= (!empty($params['enhance'])) ? '&enhance=' . $params['enhance'] : '';
                    $queryParams .= (!empty($params['quality'])) ? '&quality=' . $params['quality'] : '';
                  }

                  if(strlen ( $queryParams ) > 0 ){
                    $metadata['content_url'] .= '?'.substr($queryParams, 1);
                    $metadata['thumbnail_url'] .= '?'.substr($queryParams, 1);
                  }

                }
                //MEDIA VIDEO
                elseif ($metadata['contentType'] == 'VIDEO') {
                  $this->privateTempStore->set('embed_channel', $formatter_settings['embed_channel']);
                  if ($formatter_settings['embed_channel'] && trim($formatter_settings['embed_channel']) != "" && $formatter_settings['embed_channel'] != "all") {
                    $sources = [];
                    foreach ($metadata["sources"] as $ch => $source) {
                      if ($ch == $formatter_settings['embed_channel']) {
                        $sources[$ch] = $source;
                        break;
                      }
                    }

                    $metadata["sources"] = $sources;
                  }
                }
              }

              // Build render array.
              $elements[$delta] = [
                '#theme' => 'thron_content_embedded',
                '#contentType' => $metadata['contentType'],
                '#view_mode' => $view_mode,

                '#divId' => $uniqueDiv,

                '#metadata' => $metadata,
                '#wrapper_attributes' => new Attribute($wrapper_attributes),
                '#inner_attributes' => new Attribute($inner_attributes),
                '#attached' => $attached,
              ];
            }
          }
        }
      }
    }

    return $elements;
  }

  private function getImageSetValueByMediaName($responsiveness, $name)
  {
    foreach ($responsiveness as $key => $item) {
      if ($key == 'default') {
        continue;
      }

      if ($item['name'] == $name) {
        return $item['value'];
      }
    }
    return NULL;
  }

}
