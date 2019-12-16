<?php

namespace Drupal\thron\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;

use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;
use Drupal\thron\Plugin\media\Source\ThronMediaSource;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'THRON Embedded' formatter.
 *
 * @FieldFormatter(
 *   id = "thron_html5",
 *   label = @Translation("HTML5 Tag"),
 *   field_types = {"entity_reference"},
 *   weight = 2
 * )
 */
class ThronHTML5Formatter extends ThronFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'embed_image_as_webp' => 0,
      'embed_imageset' => 0,
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
    /** @var \Drupal\media\MediaSourceInterface $source_plugin */
    $source_plugin = $media->getSource();
    if ($source_plugin instanceof ThronMediaSource) {
      // Retrieve THRON content Metadata.
      if ($metadata = $source_plugin->getMetadata($media, NULL, $this->THRON->getPreviewLanguage())) {

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

        $is_imageset = $this->getSetting('embed_imageset');
        $skid = 'media:'.$media->id().':sessionOptions';  // session Key ID
        $opts = \Drupal::request()->getSession()->get($skid);
        $treat_as_imageset = !empty($opts) && isset($opts['treat_as_imageset']) && $opts['treat_as_imageset'];
        if (!$is_imageset && $treat_as_imageset) {
          $is_imageset = TRUE;
        }
        if ($treat_as_imageset) {
          \Drupal::request()->getSession()->remove($skid);
        }

        if ($metadata['contentType'] == 'IMAGE') {
          $ext = $this->getMediaContentExtension($metadata['id']);
          if ($ext == 'webp') {
            $elements['embed_image_as_webp'] = [
              '#type' => 'checkbox',
              '#title' => $this->t('Embed image as WEBP'),
              '#default_value' => $this->privateTempStore->get('embed_image_as_webp') ?: $this->getSetting('embed_image_as_webp'),
            ];
          }

          if ($is_imageset) {
            $elements['embed_imageset'] = [
              '#type' => 'hidden',
              '#value' => '1',
            ];
          }
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

        if ($is_imageset) {
          $elements['embed_resizing']['#options'] = [
            'noresize' => $this->t('No resize (IMAGE SET only)'),
            'fixed' => $this->t('Fixed (with aspect/ratio)'),
            'responsive' => $this->t('Responsive'),
          ];

          $elements['embed_resizing']['#default_value'] = 'noresize';
        }

        $elements['embed_imageset_info'] = [
          '#type' => 'item',
          '#markup' => '<strong>Image Set: Will resize automatically</strong>',
          '#states' => [
            'visible' => [
              'select[name="attributes[data-entity-embed-display-settings][embed_resizing]"]' => ['value' => 'noresize'],
            ],
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
          '#type' => 'item',
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


        $form_state->set('metadata', $metadata);

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

              $formatter_settings = $this->getSettings();

              $view_mode = $this->viewMode;
              $ckeditor_preview_mode = FALSE;
              if ($view_mode == '_entity_embed') {
                $route_match = \Drupal::routeMatch();
                if (strpos($route_match->getRouteName(), 'entity.node.') === 0) {
                  $view_mode = 'full';
                  $uniqueDiv = Html::getUniqueId($metadata['id']);
                  $attached['library'][] = 'thron/tracking';
                  $attached['drupalSettings']['thron']['track'] = $uniqueDiv;

                  $wrapper_attributes['class'] = [
                    'content-wrap',
                    $metadata['contentType'] == 'IMAGE' ? 'image-wrap' : 'video-wrap',
                  ];
                  $wrapper_attributes['style'] = 'position:relative;';

                  $inner_attributes['id'] = $uniqueDiv;
                  $inner_attributes['class'] = ['content-tag'];
                  $inner_attributes['style'] = 'position:absolute;top:0;left:0;width:100%;height:100%;';
                }
                else {
                  $ckeditor_preview_mode = TRUE;
                  $wrapper_attributes['class'] = ['teaser-content'];
                  $wrapper_attributes['style'] = 'position:relative;border:1px solid grey;overflow:hidden;';
                  $inner_attributes['class'] = ['thron-thumbnail'];
                  $inner_attributes['style'] = 'position:absolute;width:100%;height:auto;top:50%;left:50%;transform:translate(-50%,-50%);';
                }
              }

              if ($formatter_settings['embed_resizing'] == 'fixed') {
                $sizes = [
                  'width' => $formatter_settings['embed_resizing_fixed_width'],
                  'height' => $formatter_settings['embed_resizing_fixed_height'],
                  'link' => $formatter_settings['embed_resizing_fixed_link']
                ];
                $this->privateTempStore->set('embed_resizing_fixed', $sizes);

                $w = !empty($sizes['width']) && $sizes['width'] != "0" ? $sizes['width'] : 0;
                $h = !empty($sizes['height']) && $sizes['height'] != "0" ? $sizes['height'] : 0;

                $wrapper_attributes['style'] .= new FormattableMarkup('width:@width;height:@height;', [
                  '@width' => $w == 0 ? 'auto' : $w . 'px',
                  '@height' => $h == 0 ? 'auto' : $h . 'px',
                ]);

                if (isset($metadata['content_url_pattern'])) {
                  $metadata['content_url'] = (string) (new FormattableMarkup($metadata['content_url_pattern'], [
                    '@divArea' => $w . "x" . $h,
                  ]));
                }

                if (isset($metadata['thumbnail_url_pattern'])) {
                  $metadata['thumbnail_url'] = (string) (new FormattableMarkup($metadata['thumbnail_url_pattern'], [
                    '@divArea' => $w . "x" . $h,
                  ]));
                }
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

                // Hack for CK editor to show a width-less element as wide as possible.
                if ($ckeditor_preview_mode) {
                  $base = 800;
                  $fake_width = $base * $formatter_settings['embed_resizing_responsive_width'] / 100;
                  $wrapper_attributes['style'] .= new FormattableMarkup('width:@width;padding-top:@paddingTop;', [
                    '@width' => $fake_width . 'px',
                    '@paddingTop' => ($paddingTopBase * $formatter_settings['embed_resizing_responsive_width'] / 100) . '%',
                  ]);
                }
              }

              if ($metadata['contentType'] == 'IMAGE') {
                $this->privateTempStore->set('embed_image_as_webp', $formatter_settings['embed_image_as_webp']);
                $ext = $this->getMediaContentExtension($metadata['id']);
                if ($ext == 'webp' && $formatter_settings['embed_image_as_webp']) {
                  $metadata['content_url'] .= '?format=webp';
                }

                if ($formatter_settings['embed_imageset']) {
                  $metadata['use_picture'] = TRUE;
                  $metadata['content_url'] .= '.' . $ext;
                  $responsiveness = $this->config->get('responsive_pictures_breakpoints');
                  if(empty($responsiveness))
                    $responsiveness=$this->THRON->getBreakpointTags(true);

				          $new_imageset = [];
                  if (!empty($metadata['imageset']) && $responsiveness) {
                    foreach ($metadata['imageset'] as $media_key => $url) {
                      $new_imageset[$media_key] = [
                        'srcset' => $url,
                        'media' => $this->getImageSetValueByMediaName($responsiveness, $media_key),
                        'type' => 'image/' . $ext,
                      ];
                    }
                  }
                  $metadata['imageset'] = $new_imageset;
                }
              }

              // Build render array.
              $elements[$delta] = [
                '#theme' => 'thron_content_html5',
                '#clientId' => $this->config->get('client_id'),
                '#contentType' => $metadata['contentType'],
                '#xcontentId' => $metadata['id'],
                '#view_mode' => $view_mode,

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

  private function getImageSetValueByMediaName($responsiveness, $name) {
    foreach($responsiveness as $key => $item) {
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
