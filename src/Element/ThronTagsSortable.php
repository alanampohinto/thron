<?php

namespace Drupal\thron\Element;


use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Select;

/**
 * Class ThronTagsSortable
 *
 * @FormElement("thron_tags_sortable")
 */
class ThronTagsSortable extends Select {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#multiple' => TRUE,
      '#process' => [
        [$class, 'processSelect'],
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderSelect'],
      ],
      '#theme' => 'thron_select_sortable_widget__select',
      '#theme_wrappers' => ['form_element'],
      '#options' => [],
      '#available_tags' => [],
      '#selected_tags' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function processSelect(&$element, FormStateInterface $form_state, &$complete_form) {
    parent::processSelect($element, $form_state, $complete_form);

    $metadata = BubbleableMetadata::createFromRenderArray($element);
    $element['#attributes']['class'][] = 'form-autocomplete-thron';
    $metadata->addAttachments(['library' => ['thron/search_config']]);
    $metadata->applyTo($element);

    return $element;
  }


  /**
   * Prepares a select render element.
   */
  public static function preRenderSelect($element) {
    Element::setAttributes($element, ['id', 'name', 'size']);
    static::setAttributes($element, ['form-select', 'connected-select']);
    return $element;
  }

}