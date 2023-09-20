<?php

namespace Drupal\thron\Plugin\EntityBrowser\Widget;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\Element\EntityBrowserPagerElement;
use Drupal\entity_browser\Events\EntitySelectionEvent;
use Drupal\entity_browser\Events\Events;
use Drupal\entity_browser\WidgetValidationManager;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\thron\Exception\UnableToConnectException;
use Drupal\thron\THRONApiInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use UnexpectedValueException;

/**
 * Uses a THRON API to search and provide entity listing in a browser's widget.
 *
 * @EntityBrowserWidget(
 *   id = "thron_search",
 *   label = @Translation("THRON Search Browser"),
 *   description = @Translation("THRON DAM assets search browser.")
 * )
 */
class THRONSearch extends THRONWidgetBase {

  /**
   * Limits the amount of tags returned in the Media browser filter.
   */
  const TAG_LIST_LIMIT = 25;

  /**
   * Constant symbol used for indenting sub-categories.
   */
  const SUB_CATEGORY_INDENT = '&nbsp;&nbsp;';

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
   * The media storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * THRONSearch constructor.
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
   * @param \Drupal\thron\THRONApiInterface $thron_api
   *   THRON API service.
   * @param \Drupal\Core\Session\AccountProxyInterface $account_proxy
   *   Account proxy.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   Url generator.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
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
    THRONApiInterface $thron_api,
    AccountProxyInterface $account_proxy,
    UrlGeneratorInterface $url_generator,
    LoggerChannelFactoryInterface $logger_factory,
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory,
    CacheBackendInterface $cache,
    TimeInterface $time,
    ModuleHandlerInterface $module_handler
  ) {

    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $event_dispatcher,
      $entity_type_manager,
      $validation_manager,
      $logger_factory,
      $request_stack,
      $config_factory,
      $thron_api
    );
    $this->accountProxy = $account_proxy;
    $this->urlGenerator = $url_generator;
    $this->mediaStorage = $entity_type_manager->getStorage('media');
    $this->cache = $cache;
    $this->time = $time;
    $this->moduleHandler = $module_handler;
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
      $container->get('thron_api'),
      $container->get('current_user'),
      $container->get('url_generator'),
      $container->get('logger.factory'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('cache.thron'),
      $container->get('datetime.time'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'items_per_page' => 15,
      'tags' => [
        'enabled' => FALSE,
        'search_type' => 'default',
        'chosen_tags' => [],
        'search_autocomplete_classifications' => [],
        'search_autocomplete_depth' => 1,
      ],
      'folders' => [
        'filter_by_folder' => 'list',
      ]
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $widget_uuid = $form_state->get('widget_uuid') ?: FALSE;
    /*
     * With no UUID we can't continue building this config form.
     * @see \Drupal\thron\Form\ThronWidgetsConfig::buildForm().
     */
    if (!$widget_uuid) {
      return $form;
    }

    $form = parent::buildConfigurationForm($form, $form_state);

    $form['#attached']['library'][] = 'thron/search_config';

    $form['tags'] = [
      '#type' => 'details',
      '#title' => $this->t('Tags filtering'),
      '#open' => TRUE,
    ];

    $form['folders'] = [
      '#type' => 'details',
      '#title' => $this->t('Folders filtering'),
      '#open' => TRUE,
    ];

    $form['tags']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $this->configuration['tags']['enabled'],
    ];

    $form['tags']['search_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of tag search'),
      '#default_value' => $this->configuration['tags']['search_type'],
      '#options' => [
        'default' => $this->t('Simple select boxes'),
        'autocomplete' => $this->t('Autocomplete atomic tag'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="table[' . $widget_uuid . '][form][tags][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Autocomplete tags filter.
    $classification_options = [];
    $enabled_classifications = $this->config->get('classifications');
    $classifications = $this->THRONApi->getClassifications();
    if ($classifications) {
      foreach ($classifications as $id => $classification) {
        if (!in_array($id, $enabled_classifications) || !$enabled_classifications[$id]) {
          continue;
        }
        $name = $this->THRONApi->getSingleLocaleData($classification['names']);
        $classification_options[$id] = $name['label'];
      }

      $classifications_description = $this->t('Only chosen classifications will be searched for tags inside autocomplete<br>Please select at least one.');
    }
    else {
      $classifications_description = $this->t('No classifications found.');
    }

    $form['tags']['search_autocomplete_classifications'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Classifications to search through'),
      '#description' => $classifications_description,
      '#default_value' => $this->configuration['tags']['search_autocomplete_classifications'],
      '#options' => $classification_options,
      '#states' => [
        'visible' => [
          ':input[name="table[' . $widget_uuid . '][form][tags][search_type]"]' => ['value' => 'autocomplete'],
          ':input[name="table[' . $widget_uuid . '][form][tags][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['tags']['search_autocomplete_depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Depth of search'),
      '#description' => $this->t('Will look into found tag\'s children for even more tags'),
      '#default_value' => $this->configuration['tags']['search_autocomplete_depth'],
      '#min' => 0,
      '#max' => 100,
      '#step' => 1,
      '#size' => 40,
      '#states' => [
        'visible' => [
          ':input[name="table[' . $widget_uuid . '][form][tags][search_type]"]' => ['value' => 'autocomplete'],
          ':input[name="table[' . $widget_uuid . '][form][tags][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Select tags filter.
    $tags = $this->getParentTags();
    $options = [];
    $available_tags = [];
    $selected_tags = [];
    $configured_tags = array_combine($this->configuration['tags']['chosen_tags'], $this->configuration['tags']['chosen_tags']);
    foreach ($tags as $id => $tag) {
      $options[$id] = $tag['name'];

      if (isset($configured_tags[$id])) {
        $selected_tags[$id] = $tag['name'];
      }
      else {
        $available_tags[$id] = $tag['name'];
      }
    }

    $form['tags']['chosen_tags'] = [
      '#type' => 'thron_tags_sortable',
      '#available_tags' => $available_tags,
      '#selected_tags' => $selected_tags,
      '#default_value' => array_keys($selected_tags),
      '#options' => $options,
      '#states' => [
        'visible' => [
          ':input[name="table[' . $widget_uuid . '][form][tags][enabled]"]' => ['checked' => TRUE],
          ':input[name="table[' . $widget_uuid . '][form][tags][search_type]"]' => ['value' => 'default'],
        ],
      ],
    ];


    $form['folders']['filter_by_folder'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose folder filtering mode'),
      '#options' => [
        'list' => $this->t('Select'),
        'auto' => $this->t('Autocomplete')
      ],
      '#default_value' => $this->configuration['folders']['filter_by_folder']
    ];

    $form['items_per_page'] = [
      '#type' => 'select',
      '#title' => $this->t('Items per page'),
      '#default_value' => $this->configuration['items_per_page'],
      '#options' => ['10' => 10, '15' => 15, '25' => 25, '50' => 50],
    ];

    return $form;
  }

  /**
   * Retrieve the tags ROOT elements.
   *
   * @return array the tag elements
   */
  private function getParentTags() {
    $classifications = $this->config->get('classifications');

    if (!$classifications) {
      return [];
    }

    $cid = 'search_tags_' . $this->config->get('client_id') . "_" . implode('', $classifications);
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    $allTags = [];
    foreach ($classifications as $classificationId) {
      // Retrieve all tags for current classification.
      $tags = $this->THRONApi->getTags($classificationId);
      foreach ($tags as $tag_id => $tag) {
        if (!empty($tag['subNodeIds'])) {
          $subNodeIds = [];
          // Include non-leaf tags.
          foreach ($tag['subNodeIds'] as $subNodeId) {
            // Populate tags.
            if (isset($tags[$subNodeId])) {
              $locale_data = $this->THRONApi->getSingleLocaleData($tags[$subNodeId]['names']);
              $subNodeIds[$subNodeId] = [
                'id' => $subNodeId,
                'name' => isset($locale_data['label']) ? $locale_data['label'] : 'NOLABEL',
                'classification' => $classificationId,
              ];
            }
          }
          $locale_data = $this->THRONApi->getSingleLocaleData($tag['names']);
          $allTags[$tag_id] = [
            'id' => $tag_id,
            'name' => isset($locale_data['label']) ? $locale_data['label'] : 'NOLABEL',
            'classification' => $classificationId,
            'sub' => $subNodeIds,
          ];
        }
      }
    }

    $this->cache->set($cid, $allTags, time() + 60 * 60 * 24);

    return $allTags;
  }

  /**
   * Unset the pagination-specific values from a search request (to understand if such parameters change in time)
   */
  function getSearchParamsExcludingPagination($query) {
    $tmpRes = json_decode(json_encode($query), TRUE);
    unset($tmpRes["limit"], $tmpRes["page"], $tmpRes["total"], $tmpRes["nextPage"]);
    return $tmpRes;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);

    $has_search_permissions = $this->THRONApi->hasPermission('thron search media');
    if (!$has_search_permissions) {
      $form['actions']['submit']['#access'] = FALSE;
      $form['warning'] = [
        '#markup' => '<h4>' . $this->t('Access denied') . '</h4>',
      ];
      $form['message'] = [
        '#markup' => '<p>' . $this->t("Unable to search files on THRON DAM service. Make sure your user account has role with enough permissions.") . '</p>',
      ];
    }

    if ($form_state->getValue('errors')) {
      $form['actions']['submit']['#access'] = FALSE;
      return $form;
    }

    $form['#attached']['library'][] = 'thron/search_view';

    $form['filters'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['class' => 'thron-filters'],
    ];

    $form['filters']['content_type'] = [
      '#title' => $this->t('Content type'),
      '#type' => 'select',
      '#options' => $this->THRONApi->getContentTypes(),
      '#empty_value' => '',
      '#empty_option' => $this->t('All'),
      '#weight' => 0,
    ];

    if ($this->config->get('responsive_pictures_enable')) {
      $form['filters']['content_type']['#options']['IMAGESET'] = $this->t('Image set');
    }

    $form['filters']['search_thron'] = [
      '#type' => 'textfield',
      '#weight' => 1,
      '#title' => $this->t('Name'),
      '#attributes' => [
        'size' => 30,
      ],
    ];

    $max_option_weight = 10;

    if ($this->configuration['tags']['enabled']) {
      try {
        if ($this->configuration['tags']['search_type'] == 'default') {
          $form['filters']['tags'] = [
            '#type' => 'container',
            '#tree' => TRUE,
            '#attributes' => [
              'class' => 'tag-filters-wrapper',
            ],
            '#weight' => $max_option_weight,
          ];

          $all_tags = $this->getTagsFilterArray($this->configuration['tags']['chosen_tags']);
          foreach ($all_tags as $tag) {
            $tag_options = [];
            foreach ($tag['sub'] as $sub) {
              $key = $sub['classification'] . '_' . $sub['id'];
              $tag_options[$key] = $sub['name'];
            }

            $form['filters']['tags'][$tag['id']] = [
              '#type' => 'select',
              '#multiple' => FALSE,
              '#title' => $tag['name'],
              '#options' => $tag_options,
              '#empty_value' => '',
              '#empty_option' => $this->t('- None -'),
              '#weight' => $max_option_weight,
            ];
          }
        }
        else {
          $enabled_classifications = [];
          foreach ($this->configuration['tags']['search_autocomplete_classifications'] as $key => $item) {
            if ($item) {
              $enabled_classifications[] = $key;
            }
          }
          $classifications_string = implode("+", $enabled_classifications);
          $form['filters']['search_tag_autocomplete'] = [
            '#type' => 'thron_autocomplete',
            '#title' => $this->t('Search by tag'),
            '#autocomplete_route_name' => 'thron.autocomplete_tags',
            '#autocomplete_route_parameters' => [
              'depth' => $this->configuration['tags']['search_autocomplete_depth'],
              'classifications' => $classifications_string,
            ],
            '#weight' => $max_option_weight,
          ];
        }
      }
      catch (Exception $e) {
        (new UnableToConnectException())->logException()->displayMessage();
        $form['actions']['submit']['#access'] = FALSE;
        return $form;
      }
    }

    // Show categories filter select
    $categoriesTree  = $this->buildCategoryTree();
    $optionsCategories = null;

    $currentInterfaceLang = \Drupal::languageManager()->getCurrentLanguage();
    $langCode = strtoupper($currentInterfaceLang->getId());

    $this->buildRenderableSelect($optionsCategories, $categoriesTree['children'], $langCode);

    if ($this->configuration['folders']['filter_by_folder'] == 'list') {
      $form['filters']['categories'] = [
        '#title' => $this->t('Folder'),
        '#type' => 'select',
        '#options' => $optionsCategories,
        '#multiple' => FALSE,
        '#weight' => $max_option_weight + 5,
      ];
    } else {
      $form['filters']['categories_autocomplete'] = [
        '#type' => 'thron_autocomplete',
        '#title' => $this->t('Search by folder'),
        '#size' => 30,
        '#autocomplete_route_name' => 'thron.autocomplete_categories',
        '#autocomplete_route_parameters' => [
          'categories' => json_encode($optionsCategories),
        ],
        '#weight' => $max_option_weight + 5,
      ];
    }

    $form['filters']['ordering'] = [
      '#title' => $this->t('Order by'),
      '#type' => 'select',
      '#options' => [
        'lastUpdate_d' => $this->t('Last update - Desc'),
        'lastUpdate_a' => $this->t('Last update - Asc'),
        'creationDate_d' => $this->t('Creation date - Desc'),
        'creationDate_a' => $this->t('Creation date - Asc'),
      ],
      '#multiple' => FALSE,
      '#weight' => $max_option_weight + 5,
    ];

    $form['filters_actions'] = [
      '#type' => 'container',
    ];

    $form['filters_actions']['search_button'] = [
      '#type' => 'submit',
      '#weight' => $max_option_weight + 10,
      '#value' => $this->t('Filter'),
      '#name' => 'search_submit',
    ];

    $form['filters_actions']['reset_search_button'] = [
      '#type' => 'submit',
      '#weight' => $max_option_weight + 10,
      '#value' => $this->t('Reset'),
      '#name' => 'reset_search_submit',
      '#reset_search_button' => TRUE,
      '#access' => FALSE,
    ];

    $form['thumbnails'] = [
      '#type' => 'container',
      '#weight' => $max_option_weight + 15,
      '#attributes' => ['id' => 'thumbnails', 'class' => 'grid'],
    ];

    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element && $triggering_element['#name'] == 'search_submit') {
      EntityBrowserPagerElement::setCurrentPage($form_state);
    }
    $page = EntityBrowserPagerElement::getCurrentPage($form_state);

    // Query begin
    $query = [
      'limit' => $this->configuration['items_per_page'],
      'page' => $page,
      'total' => 1,
      'tags' => [],
    ];

    $show_reset_button = FALSE;

    if ($content_type = $form_state->getValue(['filters', 'content_type'])) {
      if ($content_type == 'IMAGESET') {
        $query['type'] = 'IMAGE';
        $breakpoints = $this->config->get('responsive_pictures_breakpoints') ?: NULL;
        if (!empty($breakpoints)) {
          $master_image_set_tag_id = $breakpoints['default'];
          $login_data = $this->THRONApi->getLoginData();
          $main_imageset_tag = $login_data['image_set_tag_info'];
          if (!empty($main_imageset_tag)) {
            $main_imageset_tag['id'] = $master_image_set_tag_id;
            $query['tags'][] = $main_imageset_tag;
          }
        }
      }
      else {
        $query['type'] = $content_type;
      }
      $show_reset_button = TRUE;
    }

    if ($keyword = $form_state->getValue(['filters', 'search_thron'])) {
      $query['keyword'] = $keyword;
      $show_reset_button = TRUE;
    }

    // Other CHOSEN tags:
    if ($selected_tags = $form_state->getValue(['filters', 'tags'])) {
      foreach ($selected_tags as $selected_tag) {
        if (empty($selected_tag)) {
          continue;
        }
        list($classification, $tag_id) = preg_split('/_/', $selected_tag);
        $query['tags'][] = [
          'id' => $tag_id,
          'classificationId' => $classification,
        ];
      }
      $show_reset_button = TRUE;
    }

    if ($selected_category = $form_state->getValue(['filters', 'categories'])) {
      $query['linkedCategories'] = $selected_category;
    }

    if ($selected_category = $form_state->getValue(['filters', 'categories_autocomplete'])) {
      $decodedCategory = json_decode($selected_category, TRUE);
      $query['linkedCategories'] = $decodedCategory[0]['id'];
    }

    // or even other TAGS from autocomplete
    if ($tag_search = $form_state->getValue(['filters','search_tag_autocomplete'])) {
      $query['tags'] = json_decode($tag_search, TRUE);
      $show_reset_button = TRUE;
    }

    $query['orderBy'] = NULL;
    if ($form_state->getValue(['filters', 'ordering'])) {
      $query['orderBy'] = $form_state->getValue(['filters', 'ordering']);
      $show_reset_button = TRUE;
    }

    if ($show_reset_button) {
      $form['filters_actions']['reset_search_button']['#access'] = TRUE;
    }

    // has the query changed? if so, we're back to page 1
    $old_hash = $form_state->get('thron_media_list_hash');

    // the hash is calculated on the search parameters, not on the pagination!
    $queryForHash=$this->getSearchParamsExcludingPagination($query);
    $key_hash = md5($this->THRONApi->gluey($queryForHash));
    if($old_hash != NULL && $old_hash != $key_hash) {
      // the search parameters have changed; return to page 1
      $form_state->set('thron_media_list_page_'.($page), NULL);
      $page=1;
    }

    $page_token = NULL;
    try {
      $page_token = $form_state->get('thron_media_list_page_'.($page));
    } catch(\Exception $ex) {}
    $query["nextPage"] = $page_token;

    $media_list = [];
    try {
      $media_list = $this->doSearch($query);
      $form_state->set('thron_media_list_hash', $key_hash);
      if(isset($media_list["nextPageToken"]) && trim($media_list["nextPageToken"]) != "")
        $form_state->set('thron_media_list_page_'.($page+1), $media_list["nextPageToken"]);
      else
        $form_state->set('thron_media_list_page_'.($page+1), "");
    }
    catch (Exception $e) {
      (new UnableToConnectException())->logException()->displayMessage();
      $form['actions']['submit']['#access'] = FALSE;
      return $form;
    }

    // Determines the date to show in media list
    $showCreated = str_contains($query['orderBy'] ?? '', 'creationDate');
    $showLastUpdated = !$showCreated;

    if (!empty($media_list['items'])) {
      foreach ($media_list['items'] as $media) {
        $media_id = $media['id'];
        $form['thumbnails']['thumbnail-' . $media_id] = [
          '#type' => 'container',
          '#attributes' => ['id' => $media_id, 'class' => ['grid-item']],
        ];
        $form['thumbnails']['thumbnail-' . $media_id]['check_' . $media_id] = [
          '#type' => 'checkbox',
          '#parents' => ['selection', $media_id],
          '#attributes' => ['class' => ['item-selector']],
        ];
        $form['thumbnails']['thumbnail-' . $media_id]['image'] = [
          '#theme' => 'thron_search_item',
          '#thumbnail_uri' => $media['thumb'],
          '#name' => $media['name'],
          '#type' => $media['type'],
          '#created' => $showCreated ? $media['created'] : NULL,
          '#updated' => $showLastUpdated ? $media['lastUpdate'] : NULL,
          '#owner' => $media['owner'],
          '#category' => $media['category'],
        ];

        if (in_array(strtoupper($media['type']), ['IMAGE', 'VIDEO', 'OTHER', 'AUDIO'])) {
          $form['thumbnails']['thumbnail-' . $media_id]['image']['#extension'] = strtoupper($media['extension']);
        }
      }

      $form['pager_eb'] = [
        '#type' => 'entity_browser_pager',
        '#total_pages' => (int) ceil($media_list['total'] / $this->configuration['items_per_page']),
        '#weight' => $max_option_weight + 20,
      ];

      // Set validation errors limit to prevent validation of filters on select.
      // We also need to set #submit to the default submit callback otherwise
      // limit won't take effect. Thank you Form API, you are very kind...
      // @see \Drupal\Core\Form\FormValidator::determineLimitValidationErrors()
      $form['actions']['submit']['#limit_validation_errors'] = [['selection']];
      $form['actions']['submit']['#submit'] = ['::submitForm'];
    }
    else {
      $form['empty_message'] = [
        '#prefix' => '<div class="empty-message">',
        '#markup' => $this->t('No assets found for current search criteria.'),
        '#suffix' => '</div>',
        '#weight' => $max_option_weight + 20,
      ];
      $form['actions']['submit']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * Returns the Array of tags to be used for filtering
   * @param $tag_ids
   *
   * @return array
   */
  public function getTagsFilterArray($tag_ids) {
    $parent_tags = $this->getParentTags();

    $tagsFilter = [];
    foreach ($tag_ids as $tag_id) {
      if (isset($parent_tags[$tag_id])) {
        $tagsFilter[$tag_id] = $parent_tags[$tag_id];
      }
    }

    return $tagsFilter;
  }

  /**
   * @param $query
   *
   * @return array
   */
  private function doSearch($query) {
    // Check type.
    $filterType = [];
    $requested_type = "";
	// TYpe is a multi select input?
    if (!empty($query['type']) && is_array($query['type'])) {
      $query['contentType'] = [];
      foreach ($query['type'] as $type) {
        if (strpos($type ?? '', '_') !== FALSE) {
          list($type, $real) = preg_split('/_/', $type);
          $query['contentType'][] = $type;
          $filterType[$type][] = $real;
        }
        else {
          $query['contentType'][] = $type;
        }
      }

	  $requested_type=$query['type'];
    }
    // Type is a normal select input?
    elseif (!empty($query['type'])) {
      if (strpos($query['type'], '_') !== FALSE) {
        list($type, $real) = preg_split('/_/', $query['type']);
        $query['contentType'][] = $type;
        $filterType[$type][] = $real;
      }
      else {
        $query['contentType'][] = $query['type'];
      }
	  $requested_type=$query['type'];
    }
    $data = $this->THRONApi->contentSearch($query);

    // Init results
    $results = [
      'items' => [],
      'total' => $data['total'],
      'nextPageToken' => $data['nextPageToken'],
      'prevPageToken' => $data['prevPageToken'],
    ];

    if (count($data['contents'])) {
      $content_types = $this->THRONApi->getContentTypes();
      $removed = 0;
      foreach (array_values($data['contents']) as $item) {
        $type = $item->contentType;
        $real_type = $this->THRONApi->getContentRealType($item);
        $final_type = $type != $real_type ? $type . '_' . $real_type : $type;
        // Filter contents.
        if (isset($filterType[$type])) {
          if (!in_array($real_type, $filterType[$type], TRUE)) {
            $removed++;
            continue;
          }
        }
		else if($requested_type != "") {
			if ($query['type'] == "PLAYLIST" && in_array($real_type, ["GALLERY","360"])) {
				$removed++;
				continue;
			}
		}

        $localized_data = $this->THRONApi->getSingleLocaleData($item->details->locales, NULL, 'locale');

        $clientId = $this->config->get('client_id');
        $pkey = $this->THRONApi->getLoginData()['pkey'];

        $thumbnail_url = "//$clientId-cdn.thron.com/delivery/public/thumbnail/$clientId/{$item->id}/$pkey/std/320x0/";
        $thumbnail_url .= "preview.jpg";
        $categoryLocalized = $this->getCategoryLocalized($item);

        $results['items'][] = [
          'id' => $item->id,
          'type' => isset($content_types[$final_type]) ? $content_types[$final_type] : $real_type,
          'name' => isset($localized_data['name']) ? $localized_data['name'] : '',
          'description' => isset($localized_data['description']) ? $localized_data['description'] : '',
          'thumb' => $thumbnail_url,
          'owner' => $item->details->owner->ownerFullName,
          'created' => $item->creationDate,
          'lastUpdate' => $item->details->lastUpdate,
          'extension' => isset($item->details->source->extension) ? $item->details->source->extension : '',
          'availableChannels' => $item->details->availableChannels,
          'category' =>  $categoryLocalized,
        ];
      }

      $results['total'] = $data['total'] - $removed;
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (!empty($trigger['#eb_widget_main_submit'])) {
      parent::validate($form, $form_state);
    }
    else {
      $form_state->setValue('selection', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    if (!empty($trigger['#eb_widget_main_submit'])) {
      try {
        /*
         * Todo: do a better cache integration, this is a stub that is not working!
         *  $api_list['media'] => $api_list['items']
         *
         * We store info about media assets that we already fetched so the media
         * entity plugin can use them and avoid doing more API requests.
         *
         * @see \Drupal\thron\Plugin\MediaEntity\Type\THRON::getField()
         */
        /*
        $selected_ids = array_keys(array_filter($form_state->getValue('selection', [])));
        if ($api_list = $form_state->get('thron_media_list')) {
          foreach ($api_list['media'] as $api_item) {
            foreach ($selected_ids as $selected_id) {
              if ($api_item['id'] == $selected_id) {
                $this->cache->set('thron_item_'.$this->config->get('client_id')."§". $selected_id, $api_item, ($this->time->getRequestTime() + 120));
              }
            }
          }
        }
        */

        $input = $form_state->getUserInput();
        if ($input['filters']['content_type'] == 'IMAGESET') {
          $form_state->set('is_imageset', TRUE);
        }

        $media = $this->prepareEntities($form, $form_state);
        array_walk($media, function (MediaInterface $media_item) {
          $media_item->save();
        });
        $this->selectEntities($media, $form_state);
      }
      catch (UnexpectedValueException $e) {
        $this->messenger->addMessage($this->t('THRON integration is not configured correctly. Please contact the site administrator.'), 'error');
      }
    }
    elseif (!empty($trigger['#reset_search_button'])) {
      $this->resetForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    if (!$this->checkType()) {
      return [];
    }

    $treat_as_imageset = $form_state->get('is_imageset') ?: FALSE;

    $medias = [];
    $selected_ids = array_keys(array_filter($form_state->getValue('selection', [])));
    /** @var \Drupal\media\MediaTypeInterface $type */
    $type = $this->mediaTypeStorage->load($this->configuration['media_type']);
    $plugin = $type->getSource();
    $source_field = $plugin->getConfiguration()['source_field'];
    foreach ($selected_ids as $thron_id) {
      $mid = $this->mediaStorage->getQuery()
        ->accessCheck(TRUE)
        ->condition($source_field, $thron_id)
        ->range(0, 1)
        ->execute();
      if ($mid) {
        $media = $this->mediaStorage->load(reset($mid));

      }
      else {
        $media = Media::create([
          'bundle' => $type->id(),
          $source_field => $thron_id,
        ]);
      }

      if ($treat_as_imageset) {
        \Drupal::request()->getSession()->set('media:'.$media->id().':sessionOptions', ['treat_as_imageset' => TRUE]);
      }

      $medias[] = $media;
    }
    return $medias;
  }


  /**
   * Dispatches event that informs all subscribers about new selected entities.
   *
   * @param array $entities
   *   Array of entities.
   */
  protected function selectEntities(array $entities, FormStateInterface $form_state) {
    $selected_entities = &$form_state->get(['entity_browser', 'selected_entities']);
    $selected_entities = array_merge($selected_entities, $entities);

    $this->eventDispatcher->dispatch(
      new EntitySelectionEvent(
        $this->configuration['entity_browser_id'],
        $form_state->get(['entity_browser', 'instance_uuid']),
        $entities
      ), Events::SELECTED);
  }

  /**
   * Resets the form elements.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function resetForm(&$form, FormStateInterface $form_state) {
    $form_state->setValues([]);
    $form_state->setUserInput([]);
    $form_state->setRebuild();
  }

  /**
   * @param array $startFromId
   */
  private function buildCategoryTree($startFromId = []) {
    $allCategories = $this->THRONApi->getCategories($startFromId);

    $tree = [];
    foreach(array_reverse($allCategories) as $categoryId => $category) {
      $item = ['id' => $categoryId, 'category' => $category['locales'], 'depth' => count($category['ancestorIds'])];
      $temp = &$tree;
      $ancestors = array_merge((!empty($category['ancestorIds']) ? $category['ancestorIds'] : []), [$categoryId]);

      foreach($ancestors as $key) {
        $temp =& $temp['children'];
        $temp =& $temp[$key];
      }

      $temp = $item;
    }

    return $tree;
  }

  public function buildRenderableSelect(&$flat, $tree, $lang) {
    foreach ($tree as $key => $item) {
      $nameLocalized = isset($item['category'][$lang]) ? $item['category'][$lang]['name'] : $item['category'][0]['name'];
      $arrayRenderable = ['#markup' => str_repeat(self::SUB_CATEGORY_INDENT, $item['depth']) . $nameLocalized];
      $flat[$item['id']] = \Drupal::service('renderer')->render($arrayRenderable);

      if (isset($item['children'])) {
        $this->buildRenderableSelect($flat, $item['children'], $lang);
      }
    }
  }

  /**
   * @param $item
   * @return array
   */
  private function getCategoryLocalized($item)
  {
    $currentInterfaceLang = \Drupal::languageManager()->getCurrentLanguage();

    $category = null;
    $categories = $this->THRONApi->getCategories($item->details->linkedCategoryIds);
    $categories = reset($categories);
    $name = false;

    foreach ($categories['locales'] as $locale) {
      if ($locale['locale'] === strtoupper($currentInterfaceLang->getId())) {
        $name = $locale['name'];
      }
    }

    $category = [$name ? $name : $categories['locales'][0]['name']];
    return $category;
  }

}
