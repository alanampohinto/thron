<?php

namespace Drupal\thron\Element;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Textfield;
use Drupal\Core\Url;

/**
 * Provides a thron api autocomplete form element for Atomic tags search.
 *
 * The #default_value accepted by this element is either an entity object or an
 * array of entity objects.
 *
 * @FormElement("thron_autocomplete")
 */
class ThronAutocomplete extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#maxlength' => 128,
      '#autocomplete_route_name' => FALSE,
      '#process' => [
        [$class, 'processAutocomplete'],
        [$class, 'processAjaxForm'],
        [$class, 'processPattern'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderThronAutocomplete'],
        [$class, 'preRenderGroup'],
      ],
      '#theme' => 'thron_input_with_hidden_helper__textfield',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && is_array($input) && isset($input['helper'])) {
      return $input['helper'];
    }
    return NULL;
  }

  /**
   * Adds autocomplete functionality to elements.
   *
   * This sets up autocomplete functionality for elements with an
   * #autocomplete_route_name property, using the #autocomplete_route_parameters
   * property if present.
   *
   * For example, suppose your autocomplete route name is
   * 'mymodule.autocomplete' and its path is
   * '/mymodule/autocomplete/{a}/{b}'. In a form array, you would create a text
   * field with properties:
   * @code
   * '#autocomplete_route_name' => 'mymodule.autocomplete',
   * '#autocomplete_route_parameters' => array('a' => $some_key, 'b' => $some_id),
   * @endcode
   * If the user types "keywords" in that field, the full path called would be:
   * 'mymodule_autocomplete/$some_key/$some_id?q=keywords'
   *
   * @param array $element
   *   The form element to process. Properties used:
   *   - #autocomplete_route_name: A route to be used as callback URL by the
   *     autocomplete JavaScript library.
   *   - #autocomplete_route_parameters: The parameters to be used in
   *     conjunction with the route name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The form element.
   */
  public static function processAutocomplete(&$element, FormStateInterface $form_state, &$complete_form) {
    $url = NULL;
    $access = FALSE;

    if (!empty($element['#autocomplete_route_name'])) {
      $parameters = isset($element['#autocomplete_route_parameters']) ? $element['#autocomplete_route_parameters'] : [];
      $url = Url::fromRoute($element['#autocomplete_route_name'], $parameters)->toString(TRUE);
      /** @var \Drupal\Core\Access\AccessManagerInterface $access_manager */
      $access_manager = \Drupal::service('access_manager');
      $access = $access_manager->checkNamedRoute($element['#autocomplete_route_name'], $parameters, \Drupal::currentUser(), TRUE);
    }

    if ($access) {
      $metadata = BubbleableMetadata::createFromRenderArray($element);
      if ($access->isAllowed()) {
        $element['#attributes']['class'][] = 'form-autocomplete-thron';
        $metadata->addAttachments(['library' => ['thron/autocomplete']]);
        // Provide a data attribute for the JavaScript behavior to bind to.
        $element['#attributes']['data-autocomplete-path'] = $url->getGeneratedUrl();
        $metadata = $metadata->merge($url);
      }
      $metadata
        ->merge(BubbleableMetadata::createFromObject($access))
        ->applyTo($element);
    }

    return $element;
  }

  /**
   * @param array $element
   *
   * @return array
   *   The form element.
   */
  public static function preRenderThronAutocomplete($element) {
    $element['#attributes']['type'] = 'text';
    $element['#attributes']['data-helper-name'] = $element['#name'].'[helper]';

    Element::setAttributes($element, ['id', 'name', 'value', 'size', 'maxlength', 'placeholder']);
    static::setAttributes($element, ['form-text', 'form-text-with-hidden-helper']);

    return $element;
  }

}
