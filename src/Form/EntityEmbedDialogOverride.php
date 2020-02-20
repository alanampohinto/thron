<?php

namespace Drupal\thron\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\SetDialogTitleCommand;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\editor\EditorInterface;
use Drupal\embed\EmbedButtonInterface;
use Drupal\entity_browser\Events\Events;
use Drupal\entity_browser\Events\RegisterJSCallbacks;
use Drupal\entity_embed\EntityEmbedDisplay\EntityEmbedDisplayManager;
use Drupal\Component\Serialization\Json;
use Drupal\entity_embed\Form\EntityEmbedDialog;
use Drupal\media\Entity\Media;
use Drupal\thron\Plugin\media\Source\ThronMediaSource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Overrides the entity embed form from module for THRON needs:
 */
class EntityEmbedDialogOverride extends EntityEmbedDialog {

  /**
   * Form constructor for the entity embedding step.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function buildEmbedStep(array $form, FormStateInterface $form_state) {
    // Entity element is calculated on every AJAX request/submit.
    // See self::buildForm().
    $entity_element = $form_state->get('entity_element');
    /** @var \Drupal\embed\EmbedButtonInterface $embed_button */
    $embed_button = $form_state->get('embed_button');
    /** @var \Drupal\editor\EditorInterface $editor */
    $editor = $form_state->get('editor');
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $form_state->get('entity');
    $values = $form_state->getValues();

    $form['#title'] = $this->t('Embed @type', ['@type' => $entity->getEntityType()->getLowercaseLabel()]);

    $entity_label = $entity->label();

    $form['entity'] = [
      '#type' => 'item',
      '#title' => $this->t('Selected entity'),
      '#markup' => $entity_label,
    ];
    $form['attributes']['data-entity-type'] = [
      '#type' => 'hidden',
      '#value' => $entity_element['data-entity-type'],
    ];
    $form['attributes']['data-entity-uuid'] = [
      '#type' => 'hidden',
      '#value' => $entity_element['data-entity-uuid'],
    ];

    if (!empty($entity_element['data-langcode'])) {
      $form['attributes']['data-langcode'] = [
        '#type' => 'hidden',
        '#value' => $entity_element['data-langcode'],
      ];
    }

    // Build the list of allowed Entity Embed Display plugins.
    $display_plugin_options = $this->getDisplayPluginOptions($embed_button, $entity);

    // If the currently selected display is not in the available options,
    // use the first from the list instead. This can happen if an alter
    // hook customizes the list based on the entity.
    if (!isset($display_plugin_options[$entity_element['data-entity-embed-display']])) {
      $entity_element['data-entity-embed-display'] = key($display_plugin_options);
    }

    // The default Entity Embed Display plugin has been deprecated by the
    // rendered entity field formatter.
    if ($entity_element['data-entity-embed-display'] === 'default') {
      $entity_element['data-entity-embed-display'] = 'entity_reference:entity_reference_entity_view';
    }

    $form['attributes']['data-entity-embed-display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display as'),
      '#options' => $display_plugin_options,
      '#default_value' => $entity_element['data-entity-embed-display'],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updatePluginConfigurationForm',
        'wrapper' => 'data-entity-embed-display-settings-wrapper',
        'effect' => 'fade',
      ],
      // Hide the selection if only one option is available.
      '#access' => count($display_plugin_options) > 1,
    ];

    //==========================================================================
    // OVERRIDE LOGIC
    //==========================================================================
    $source = $entity instanceof Media ? $entity->getSource() : NULL;
    if ($source instanceof ThronMediaSource) {
      $metadata = $source->getMetadata($entity);
      $form['attributes']['data-entity-embed-display']['#options'] = [
        'entity_reference:thron_embedded' => 'THRON Player',
        'entity_reference:thron_html5' => 'HTML5 Tag',
      ];
      if (!in_array($metadata['contentType'], ['IMAGE', 'VIDEO'])) {
        unset($form['attributes']['data-entity-embed-display']['#options']['entity_reference:thron_html5']);
        $form['attributes']['data-entity-embed-display']['#default_value'] = 'entity_reference:thron_embedded';
        $entity_element['data-entity-embed-display'] = 'entity_reference:thron_embedded';
      }

      $skid = 'media:'.$entity->id().':sessionOptions';  // session Key ID
      $opts = \Drupal::request()->getSession()->get($skid);
      $treat_as_imageset = !empty($opts) && isset($opts['treat_as_imageset']) && $opts['treat_as_imageset'];

      if ($metadata['contentType'] == 'IMAGE' && $treat_as_imageset) {
        $form['entity']['#title'] = $this->t('Selected Image Set');
        unset($form['attributes']['data-entity-embed-display']['#options']['entity_reference:thron_embedded']);
        $form['attributes']['data-entity-embed-display']['#default_value'] = 'entity_reference:thron_html5';
        $entity_element['data-entity-embed-display'] = 'entity_reference:thron_html5';
      }
    }
    //==========================================================================
    $form['attributes']['data-entity-embed-display-settings'] = [
      '#type' => 'container',
      '#prefix' => '<div id="data-entity-embed-display-settings-wrapper">',
      '#suffix' => '</div>',
    ];
    $form['attributes']['data-embed-button'] = [
      '#type' => 'value',
      '#value' => $embed_button->id(),
    ];
    $plugin_id = !empty($values['attributes']['data-entity-embed-display']) ? $values['attributes']['data-entity-embed-display'] : $entity_element['data-entity-embed-display'];
    if (!empty($plugin_id)) {
      if (empty($entity_element['data-entity-embed-display-settings'])) {
        $entity_element['data-entity-embed-display-settings'] = [];
      }
      elseif (is_string($entity_element['data-entity-embed-display-settings'])) {
        $entity_element['data-entity-embed-display-settings'] = Json::decode($entity_element['data-entity-embed-display-settings']);
      }
      $display = $this->entityEmbedDisplayManager->createInstance($plugin_id, $entity_element['data-entity-embed-display-settings']);
      $display->setContextValue('entity', $entity);
      $display->setAttributes($entity_element);
      $form['attributes']['data-entity-embed-display-settings'] += $display->buildConfigurationForm($form, $form_state);
    }

    // When Drupal core's filter_align is being used, the text editor may
    // offer the ability to change the alignment.
    if ($editor->getFilterFormat()->filters('filter_align')->status) {
      $form['attributes']['data-align'] = [
        '#title' => $this->t('Align'),
        '#type' => 'radios',
        '#options' => [
          '' => $this->t('None'),
          'left' => $this->t('Left'),
          'center' => $this->t('Center'),
          'right' => $this->t('Right'),
        ],
        '#default_value' => isset($entity_element['data-align']) ? $entity_element['data-align'] : '',
        '#wrapper_attributes' => ['class' => ['container-inline']],
        '#attributes' => ['class' => ['container-inline']],
      ];
    }

    // When Drupal core's filter_caption is being used, the text editor may
    // offer the ability to add a caption.
    if ($editor->getFilterFormat()->filters('filter_caption')->status) {
      $form['attributes']['data-caption'] = [
        '#title' => $this->t('Caption'),
        '#type' => 'textarea',
        '#rows' => 3,
        '#default_value' => isset($entity_element['data-caption']) ? Html::decodeEntities($entity_element['data-caption']) : '',
        '#element_validate' => ['::escapeValue'],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => !empty($this->entityBrowserSettings['display_review']) ? '::submitAndShowReview' : '::submitAndShowSelect',
        'event' => 'click',
      ],
    ];
    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Embed'),
      '#button_type' => 'primary',
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitEmbedStep',
        'event' => 'click',
      ],
    ];

    return $form;
  }

}
