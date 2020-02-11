<?php

namespace Drupal\thron\Plugin\EntityBrowser\Widget;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\thron\THRONApiInterface;
use Drupal\thron\Exception\BundleNotThronException;
use Drupal\thron\Exception\BundleNotExistException;
use Drupal\thron\Exception\UnableToConnectException;
use Drupal\thron\Plugin\media\Source\ThronMediaSource;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\entity_browser\WidgetBase;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\thron\Utils\THRONApiUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Base class for THRON Entity browser widgets.
 */
abstract class THRONWidgetBase extends WidgetBase {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The configuration of Thron.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * THRON API service.
   *
   * @var \Drupal\thron\THRONApiInterface
   */
  protected $THRONApi;

  /**
   * Media type storage
   * 
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaTypeStorage;


  /**
   * THRONWidgetBase constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\entity_browser\WidgetValidationManager $validation_manager
   *   The Widget Validation Manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\thron\THRONApiInterface $thron_api
   *   The THRON Api interface.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EventDispatcherInterface $event_dispatcher,
    EntityTypeManagerInterface $entity_type_manager,
    WidgetValidationManager $validation_manager,
    LoggerChannelFactoryInterface $logger_factory,
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory,
    THRONApiInterface $thron_api
  ) {

    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $event_dispatcher,
      $entity_type_manager,
      $validation_manager
    );

    $this->loggerFactory = $logger_factory;
    $this->mediaTypeStorage = $entity_type_manager->getStorage('media_type');
    $this->requestStack = $request_stack;
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
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.entity_browser.widget_validation'),
      $container->get('logger.factory'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('thron_api'),
      $container->get('')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'media_type' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['media_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Media type'),
      '#default_value' => $this->configuration['media_type'],
      '#required' => TRUE,
      '#options' => [],
    ];

    $media_types = $this->mediaTypeStorage->loadMultiple();
    foreach ($media_types as $type) {
      /** @var \Drupal\media\MediaTypeInterface $type */
      if ($type->getSource() instanceof ThronMediaSource) {
        $form['media_type']['#options'][$type->id()] = $type->label();
      }
    }

    if (empty($form['media_type']['#options'])) {
      $form['media_type']['#disabled'] = TRUE;
      $form['media_type']['#description'] = $this->t('You must @create_type before using this widget.', [
        '@create_type' => Link::createFromRoute($this->t('create a THRON media type'), 'entity.media_type.add_form')
          ->toString(),
      ]);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);

    if (!$this->checkType()) {
      $form_state->setValue('errors', TRUE);
      return $form;
    }

    // Check if the API configuration is in place and exit early if not.
    $keys = [
      'consumer_key',
      'consumer_secret',
      'token',
      'token_secret',
      'account_domain',
    ];
    foreach ($keys as $key) {
      if ($this->config->get($key) === '') {
        $form_state->setValue('errors', TRUE);
        (new UnableToConnectException())->logException()->displayMessage();
        return $form;
      }
    }

    // Require Thron login if we don't have a valid access token yet.
    // If we are submitting "Reload after submit" button right now we also need
    // to add it to the form for submission to work as expected. When the form
    // will be rebuild after the submit on same request we won't add it anymore.
    if (!$this->THRONApi->hasAccessToken() || (
        $this->requestStack->getCurrentRequest()->getMethod() == 'POST' &&
        $this->requestStack->getCurrentRequest()->request->get('op') == 'Reload after submit' &&
        $form_state->isProcessingInput() === NULL
    )) {

      $form_state->setValue('errors', TRUE);

      $form['message'] = [
        '#markup' => $this->t('You need to <a href=":url" target="_blank" class="thron-login-link">log into THRON</a> before importing assets.', [
          ':url' => Url::fromRoute('thron.configuration_form')->toString(),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];

      $form['reload'] = [
        '#type' => 'button',
        '#value' => 'Reload after submit',
      ];

      return $form;
    }

    return $form;
  }

  /**
   * Check that media type is properly configured.
   *
   * @return bool
   *   Returns TRUE if media type is configured correctly.
   */
  protected function checkType() {
    /** @var \Drupal\media\MediaTypeInterface $type */
    $type = $this->mediaTypeStorage->load($this->configuration['media_type']);

    if (!$type) {
      (new BundleNotExistException($this->configuration['media_type']))
        ->logException()->displayMessage();
      return FALSE;
    }
    elseif (!($type->getSource() instanceof ThronMediaSource)) {
      (new BundleNotThronException($type->id()))
        ->logException()->displayMessage();
      return FALSE;
    }
    return TRUE;
  }

}
