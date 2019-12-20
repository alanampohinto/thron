<?php

namespace Drupal\thron\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\thron\Plugin\media\Source\ThronMediaSource;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\thron\THRONApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class ThronEmbedFormatter extends ThronFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'embed_template' => 'default',
      'embed_resizing' => 'fixed',
      'embed_resizing_fixed_width' => NULL,
      'embed_resizing_fixed_height' => NULL,
      'embed_resizing_fixed_link' => 1,
      'embed_resizing_responsive_width' => 100,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    /** @var \Drupal\media\Entity\Media $entity */
    $media = $form_state->get('entity');
    $source_plugin = $media->getSource();
    if ($source_plugin instanceof ThronMediaSource) {
      // Retrieve THRON content Metadata.
      if ($metadata = $source_plugin->getMetadata($media, NULL, $this->THRON->getPreviewLanguage())) {
        $form_state->set('metadata', $metadata);
        $form_state->set('formatter', 'thron_embedded');

        $elements['#attached']['library'][] = 'thron/formatter_resizing_config';

        $default_width = 600;
        $default_height = 400;

        $ar_label = $metadata['aspect_ratio'] ?: '600:400';
        if ($metadata['contentType'] == 'IMAGE') {
          $ar = $metadata['width'] / $metadata['height'];
          $default_width = $metadata['width'];
          $default_height = $metadata['height'];
        }
        elseif($metadata['contentType'] == 'VIDEO') {
          list($w, $h) = explode(":", $metadata['aspect_ratio']);
          $ar = $w / $h;
          $default_height = ceil($default_width / $ar);
        }
        else {
          list($w, $h) = explode(":", $metadata['aspect_ratio']);
          $ar =  ($w / $h);
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
          $defaultAdded = false;

          $options = [];

          if(isset($data['default_templates']['default'])) {
            $options[$data['default_templates']['default']] = $this->t('Default');
            $defaultAdded = true;
          }

          if(isset($data['default_templates']['noSkin'])) {
            $options[$data['default_templates']['noSkin']] = $this->t('noSkin');
            $defaultAdded = true;
          }

          if($defaultAdded) $options['---------'] = []; // separator
          foreach ($templates as $template) {
            if(
              (isset($data['default_templates']['default']) && $template['id'] != $data['default_templates']['default']) &&
              (isset($data['default_templates']['noSkin']) && $template['id'] != $data['default_templates']['noSkin'])
            ) {
              if (isset($options[$template['id']])) {
                $options[$template['id']] .= '  ('.$template['name'].')';
              }
              else {
                $options[$template['id']] = $template['name'];
              }
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
      }
      else {
        $elements['media_info_error'] = [
          '#type' => 'item',
          '#markup' => $this->t('Can\'t access the media info. Something\'s gone wrong'),
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
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
              if ($view_mode == '_entity_embed') {
                $route_match = \Drupal::routeMatch();
                if (strpos($route_match->getRouteName(), 'entity.node.') === 0) {
                  $view_mode = 'full';

                  $login_data = $this->THRON->getLoginData();

                  // is there already an embed code for this content?
                  $node = \Drupal::routeMatch()->getParameter('node');
                  if($node) {
                    try {
                      $nid = $node->id();
                    } catch(\Exception $ex) {
                      $nid = false;
                    }
                  } else $nid = false;
				  
                  if($nid) {
                    $embedCodeId = $this->THRON->getThronMediaEmbedId($metadata['id'], $nid, $formatter_settings['embed_template']);
                    if(!$embedCodeId) {
                      if ($disguisedToken = $this->THRON->impersonateApp()) {
                        // find the label for this template
                        $template_settings = $this->THRON->getVideoPlayerTemplateData($formatter_settings['embed_template']);
                        $templateLabel = "unknown";
                        if($template_settings)
                          if(isset($template_settings["item"]["name"]))
                            $templateLabel = $template_settings["item"]["name"];
                          elseif(isset($template_settings["name"]))
                            $templateLabel = $template_settings["name"];
                        $context = $login_data['tracking_context'];
                        $embed_player_code = $this->THRON->insertPlayerEmbedCode($formatter_settings['embed_template'], $templateLabel, $context, $metadata['id'], $disguisedToken);
                        $displaySettings['embed_player_code'] = $embed_player_code['item'];
                        $embedCodeId = $embed_player_code['item']['id'];
                        $res = $this->THRON->setThronMediaEmbedId($metadata['id'], $nid, $formatter_settings['embed_template'], $embedCodeId);
                      }
                    }
                  } else $embedCodeId = false;
                          
                  // get the folder pkey from the application's settings
                  $pkey = $login_data['pkey'];
          
                  $attached['library'][] = 'thron/universal_player';
                  $attached['library'][] = 'thron/formatter';
                  $attached['drupalSettings']['thron']['players'][$uniqueDiv] = [
                    'clientId' => $this->config->get('client_id'),
                    'xcontentId' => $metadata['id'],
                    'sessId' => $pkey, 
                    'language' => $language,
                  ];
				  
                  if($nid && $embedCodeId) 
                    $attached['drupalSettings']['thron']['players'][$uniqueDiv]['embedCodeId'] = $embedCodeId;

                  $wrapper_attributes['class'] = ['player-wrap'];
                  $wrapper_attributes['style'] = 'position:relative;';
                  $inner_attributes['class'] = ['player-placeholder'];
                  $inner_attributes['style'] = 'position:absolute;width:100%;height:100%;top:0;';
              }
              else {
                $wrapper_attributes['class'] = ['teaser-content'];
                $wrapper_attributes['style'] = 'position:relative;border:1px solid grey;overflow:hidden;';
                $inner_attributes['class'] = ['thron-thumbnail'];
                $inner_attributes['style'] = 'position:absolute;width:100%;height:auto;top:50%;transform:translateY(-50%);';
              }
            }

              if ($formatter_settings['embed_resizing'] == 'fixed') {
                $sizes = [
                  'width' => $formatter_settings['embed_resizing_fixed_width'],
                  'height' => $formatter_settings['embed_resizing_fixed_height'],
                  'link' => $formatter_settings['embed_resizing_fixed_link']
                ];
                $this->privateTempStore->set('embed_resizing_fixed', $sizes);

                $width  = !empty($sizes['width']) && $sizes['width'] !== "0" ? $sizes['width'] : NULL;
                $height = !empty($sizes['height']) && $sizes['height'] !== "0" ? $sizes['height'] : NULL;

                if (empty($sizes['width']) || empty($sizes['height'])) {
                  list($w, $h) = explode(":", $metadata['aspect_ratio']);
                  $ar = $w / $h;
                  if (!$height) { $height = $width / $ar; }
                  if (!$width) { $width = $height * $ar; }
                }

                $wrapper_attributes['style'] .= new FormattableMarkup('width:@width;height:@height;', [
                  '@width' => $width.'px',
                  '@height' => $height.'px',
                ]);
              }
              else {
                $this->privateTempStore->set('embed_resizing_responsive', [
                  'width' => $formatter_settings['embed_resizing_responsive_width'],
                ]);

                $paddingTopBase = '15';
                if(isset($metadata['height']) && isset($metadata['width'])) {
                  $paddingTopBase = $metadata['height'] / $metadata['width'] * 100;
                }
                elseif (isset($metadata['aspect_ratio'])) {
                  list($w, $h) = explode(":", $metadata['aspect_ratio']);
                  $paddingTopBase = $h / $w * 100;
                }

                $wrapper_attributes['style'] .= new FormattableMarkup('width:@width;padding-top:@paddingTop;', [
                  '@width' => $formatter_settings['embed_resizing_responsive_width'].'%',
                  '@paddingTop' => ($paddingTopBase * $formatter_settings['embed_resizing_responsive_width'] / 100) . '%',
                ]);
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

}
