<?php


namespace Drupal\thron\Plugin\Field\FieldFormatter;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\thron\Plugin\media\Source\ThronMediaSource;
use Drupal\thron\THRONApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ThronFormatterBase extends FormatterBase implements ContainerFactoryPluginInterface {
  /**
   * The config for THRON
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The THRON API service.
   *
   * @var \Drupal\thron\THRONApiInterface
   */
  protected $THRON;

  /**
   * Renderer object.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The private TempStore containing previously chosen settings.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $privateTempStore;

  /**
   * @var string
   */
  private $tempStoreKey = 'thron_per_user_defaults';

  /**
   * Constructs a THRONFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Render\RendererInterface $renderer_object
   *   Renderer object.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\thron\THRONApiInterface $thron_api
   *   The THRON API service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_tempStore_factory
   *   The Private TempStore Factory service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings,
                              ConfigFactoryInterface $config_factory,
                              AccountProxyInterface $current_user,
                              RendererInterface $renderer_object,
                              EntityFieldManagerInterface $entity_field_manager,
                              EntityTypeManagerInterface $entity_type_manager,
                              THRONApiInterface $thron_api,
                              PrivateTempStoreFactory $private_tempStore_factory
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->config = $config_factory->get('thron.settings');
    $this->currentUser = $current_user;
    $this->THRON = $thron_api;
    $this->renderer = $renderer_object;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->privateTempStore = $private_tempStore_factory->get($this->tempStoreKey);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('renderer'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('thron_api'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if ($field_definition->getType() == 'entity_reference') {
      if ($field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media') {
        if (strpos($field_definition->getSetting('handler'), 'default:') === 0) {
          $handler_settings = $field_definition->getSetting('handler_settings');
          if ($handler_settings['target_bundles'] === NULL) {
            return TRUE;
          }
          elseif (is_array($handler_settings['target_bundles'])) {
            foreach ($handler_settings['target_bundles'] as $bundle) {
              /** @var \Drupal\media\MediaTypeInterface $type */
              $type = \Drupal::entityTypeManager()
                ->getStorage('media_type')
                ->load($bundle);
              if ($type->getSource() instanceof ThronMediaSource) {
                return TRUE;
              }
            }
          }
        }
        else {
          // If some other selection plugin than default is used we can't
          // reliably determine if we apply or not so we allow.
          return TRUE;
        }
      }
    }
    return FALSE;
  }
}