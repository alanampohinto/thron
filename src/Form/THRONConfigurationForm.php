<?php

namespace Drupal\thron\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\State\StateInterface;
use Drupal\thron\Exception\ThronException;
use Drupal\thron\THRONApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure THRON to enable application access.
 *
 * @package Drupal\thron\Form
 */
class THRONConfigurationForm extends ConfigFormBase {

  /**
   * THRON api service.
   *
   * @var \Drupal\thron\THRONApiInterface
   *   THRON api service.
   */
  protected $THRONApi;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The State Interface
   *
   * @var \Drupal\Core\StateInterface
   */
  protected $state;

  /**
   * Constructs a THRONConfigurationForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\thron\THRONApiInterface $thron_api
   *   The THRON API service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RendererInterface $renderer, StateInterface $state, THRONApiInterface $thron_api) {
    parent::__construct($config_factory);

    $this->renderer = $renderer;
    $this->THRONApi = $thron_api;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('renderer'),
      $container->get('state'),
      $container->get('thron_api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'thron_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['thron.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('thron.settings');
    $no_conf  = empty($config->get('app_key'));
    $no_conf &= empty($config->get('app_id'));
    $no_conf &= empty($config->get('client_id'));

    // $lastUpdate = $this->state->get(THRONApi::THRON_LAST_UPDATE_STATE_KEY, 0);
    $form['credentials'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Connection'),
    ];

    $form['credentials']['client_id'] = [
      '#required' => TRUE,
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#parents' => ['credentials', 'client_id'],
      '#default_value' => $config->get('client_id'),
      '#description' => $this->t('Provide the THRON service code ("clientID"). For more information check <a href="@url">THRON knowledge base</a>.', [
        '@url' => 'https://help.thron.com',
      ]),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['credentials']['app_id'] = [
      '#required' => TRUE,
      '#type' => 'textfield',
      '#title' => $this->t('App ID'),
      '#parents' => ['credentials', 'app_id'],
      '#default_value' => $config->get('app_id'),
      '#description' => $this->t('Provide the THRON application ID ("appId"). For more information check <a href="@url">THRON knowledge base</a>.', [
        '@url' => 'https://help.thron.com',
      ]),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['credentials']['app_key'] = [
      '#required' => TRUE,
      '#type' => 'textfield',
      '#title' => $this->t('App Key'),
      '#parents' => ['credentials', 'app_key'],
      '#default_value' => $config->get('app_key'),
      '#description' => $this->t('Provide the THRON application key ("appKey"). For more information check <a href="@url">THRON knowledge base</a>.', [
        '@url' => 'https://help.thron.com',
      ]),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['global'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Global Settings'),
    ];

    $options = [
      'EN' => $this->t('English'),
      'ES' => $this->t('Spanish'),
      'FR' => $this->t('French'),
      'IT' => $this->t('Italian'),
      'DE' => $this->t('German'),
      'SL' => $this->t('Slovenian'),
      'PT' => $this->t('Portuguese'),
      'ZH' => $this->t('Chinese'),
    ];
    $form['global']['preview_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Preview Language'),
      '#options' => $options,
      '#default_value' => $this->THRONApi->getPreviewLanguage(),
    ];

    $form['cache'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cache management'),
      '#description' => $this->t('Each value expressed in seconds'),
    ];

    $form['cache']['cache_max_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Default'),
      '#description' => $this->t('Used for Thumbnails, Tags info, Classifications info, Content details...'),
      '#min' => 0,
      '#step' => 10,
      '#size' => 10,
      '#default_value' => $config->get('cache_max_age') ?: THRON_CACHE_MAX_AGE,
      '#required' => TRUE,
    ];

    $form['cache']['login_cache_max_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Login'),
      '#description' => $this->t('Used only for login data.'),
      '#min' => 0,
      '#step' => 10,
      '#size' => 10,
      '#default_value' => $config->get('login_cache_max_age') ?: THRON_LOGIN_CACHE_MAX_AGE,
      '#required' => TRUE,
    ];

    $classifications = !$no_conf ? $this->THRONApi->getClassifications() : FALSE;
    if ($classifications) {
      $form['classifications_fs'] = [
        '#type' => 'details',
        '#title' => $this->t('Classifications'),
        '#open' => TRUE,
      ];

      $options = [];
      foreach ($classifications as $id => $classification) {
        $names = array_filter($classification['names'], function ($arr) {
          return $arr['lang'] == 'EN';
        });
        $name = !empty($names) ? reset($names) : reset($classification['names']);
        $options[$id] = $name['label'];
      }
      $form['classifications_fs']['classifications'] = [
        '#type' => 'checkboxes',
        '#options' => $options,
        '#default_value' => $config->get('classifications') ? $config->get('classifications') : [],
      ];

      $form['classifications_fs']['avoid_classifications'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Do not use Classifications'),
        '#default_value' => $config->get('avoid_classifications'),
      ];
    }

    if (!$no_conf) {
      $form['responsive_pictures'] = [
        '#type' => 'details',
        '#title' => $this->t('Responsive breakpoints'),
        '#open' => $config->get('responsive_pictures_enable'),
      ];

      $form['responsive_pictures']['responsive_pictures_enable'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Responsive Image Sets configuration'),
        '#default_value' => $config->get('responsive_pictures_enable'),
      ];

      $form['responsive_pictures']['table'] = [
        '#type' => 'fieldset',
        '#states' => [
          'visible' => [
            'input[name="responsive_pictures_enable"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['responsive_pictures']['table']['breakpoints'] = [
        '#type' => 'table',
        '#tree' => TRUE,
        '#empty' => $this->t('Define some breakpoints'),
        '#header' => [
          $this->t('#ID'),
          $this->t('Tag name'),
          $this->t('Default'),
          $this->t('Media breakpoint'),
        ],
      ];

      $image_set_brkp_tags = $this->getBreakpointTags();
      $default_breakpoints = $config->get('responsive_pictures_breakpoints') ?: [];

      $ak = array_keys($image_set_brkp_tags);
      $default_radio = reset($ak);
      $default_radio = isset($default_breakpoints['default']) ? $default_breakpoints['default'] : $default_radio;

      $default_value_map = [
        'image-set-master' => '*',
        'mobile' => '(min-width: 500px max-width: 799px)',
        'portrait' => '(max-width: 499px)',
        'tablet' => '(min-width: 800px and max-width: 1199px)',
        'desktop' => '(min-width: 1200px)',
      ];
      foreach ($image_set_brkp_tags as $tag_id => $tag) {

        $form['responsive_pictures']['table']['breakpoints'][$tag_id]['id'] = [
          '#markup' => '<strong>' . $tag_id . '</strong>',
          '#wrapper_attributes' => [
            'style' => 'width:280px',
          ],
        ];

        $form['responsive_pictures']['table']['breakpoints'][$tag_id]['name'] = [
          '#prefix' => '<em>' . $tag['pretty-id'] . '</em>',
          '#type' => 'hidden',
          '#value' => $tag['pretty-id'],
          '#wrapper_attributes' => [
            'style' => 'width:150px',
          ],
        ];

        $form['responsive_pictures']['table']['breakpoints'][$tag_id]['default'] = [
          '#type' => 'radio',
          '#title' => $this->t('Default'),
          '#title_display' => 'invisible',
          '#name' => 'breakpoints[default]',
          '#return_value' => $tag_id,
          '#value' => $default_radio,
        ];

        $default_possible_value = isset($default_value_map[$tag['pretty-id']]) ? $default_value_map[$tag['pretty-id']] : '';
        $form['responsive_pictures']['table']['breakpoints'][$tag_id]['value'] = [
          '#type' => 'textfield',
          '#title' => $this->t('CSS Breakpoint'),
          '#title_display' => 'invisible',
          '#default_value' => isset($default_breakpoints[$tag_id]) ? $default_breakpoints[$tag_id]['value'] : $default_possible_value,
          '#size' => 60,
          '#states' => [
            'required' => [
              'input[name="responsive_pictures_enable"]' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }

    $form['#cache'] = [
      'max-age' => 0,
    ];
    $form['#attached']['library'][] = 'thron/config_form';

    return parent::buildForm($form, $form_state);
  }

  private function getBreakpointTags() {
    return $this->THRONApi->getBreakpointTags();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $credentials = $form_state->getValue('credentials');
    foreach (['client_id', 'app_key'] as $name) {
      if (!ctype_alnum($credentials[$name])) {
        $form_state->setError($form['credentials'][$name], $this->t('@label needs to contain only letters and numbers.', [
            '@label' => $form['credentials'][$name]['#title']->render(),
        ]));
      }
    }

    preg_match('/CS-(.+)/', $credentials["app_id"], $preg_test);
    if ((count($preg_test) == 0) || !ctype_alnum(str_replace("-", "", $credentials['app_id']))) {
      $form_state->setError($form['credentials']['app_id'], $this->t('app_id is empty or invalid.', [
          '@label' => $form['credentials']['app_id']['#title']->render(),
      ]));
    }

    // Makes sure we don't have a leading slash in the domain url.
    if ($form_state->getValue('test_connection')) {
      if (!$form_state::hasAnyErrors() && !$this->testApiConnection($credentials['client_id'], $credentials['app_id'], $credentials['app_key'])) {
        $form_state->setErrorByName('credentials', $this->t('Could not establish a connection with THRON. Check your credentials or <a href=":support">contact support.</a>', [':support' => 'mailto:support@thron.com']));
      }
    }

    $config = \Drupal::config('thron.settings');
    $is_initial_save = empty($config->get('app_key'));
    $is_initial_save &= empty($config->get('app_id'));
    $is_initial_save &= empty($config->get('client_id'));

    if (!$is_initial_save) {
      $no_classifications = empty($form_state->getValue('classifications')) ||
        array_reduce($form_state->getValue('classifications'), function ($carry, $item) {
          return $carry && empty($item);
        }, TRUE);

      $avoid_classifications = $form_state->getValue('avoid_classifications');
      if ($no_classifications && !$avoid_classifications) {
        $form_state->setErrorByName('classifications', $this->t('You must select at least a classification or you must choose to avoid them.'));
        $form_state->setErrorByName('avoid_classifications', '');
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = \Drupal::configFactory()->getEditable('thron.settings');
    $credentials = $form_state->getValue('credentials');

    // Connection test.
    try {
      $this->THRONApi->loginApp($credentials);
    }
    catch (ThronException $e) {
      $e->displayMessage();
      $e->logException();
      return;
    }

    // Booleans.
    $is_initial_save = $config->get('app_key') === '';
    $is_initial_save &= $config->get('app_id') === '';
    $is_initial_save &= $config->get('client_id') === '';

    $login_data_changed = $config->get('app_key') !== $credentials['app_key'];
    $login_data_changed |= $config->get('app_id') !== $credentials['app_id'];
    $login_data_changed |= $config->get('client_id') !== $credentials['client_id'];

    $preview_language = $form_state->getValue('preview_language');
    $preview_language_changed = $config->get('preview_language') !== $preview_language;

    $cache_changed = FALSE;
    $cache_max_age = $form_state->getValue('cache_max_age');
    if ($cache_max_age !== $config->get('cache_max_age')) {
      $cache_changed = TRUE;
    }
    $login_cache_max_age = $form_state->getValue('login_cache_max_age');
    if (!$cache_changed && $login_cache_max_age !== $config->get('login_cache_max_age')) {
      $cache_changed = TRUE;
    }

    $responsive_pictures_enable = $form_state->getValue('responsive_pictures_enable');
    if ($responsive_pictures_enable) {
      $responsive_pictures_breakpoints = array_map(function($item){
        if (is_array($item) && isset($item['default'])) {
          unset($item['default']);
        }
        return $item;
      }, $form_state->getValue('breakpoints'));
    }
    else {
      $responsive_pictures_breakpoints = [];
    }

    // Save configdata.
    $config
      ->set('app_key', $credentials['app_key'])
      ->set('app_id', $credentials['app_id'])
      ->set('client_id', $credentials['client_id'])
      ->set('preview_language', $preview_language)
      ->set('cache_max_age', $cache_max_age)
      ->set('login_cache_max_age', $login_cache_max_age)
      ->set('responsive_pictures_enable', $responsive_pictures_enable)
      ->set('responsive_pictures_breakpoints', $responsive_pictures_breakpoints)
      ->save();

    if (!$is_initial_save && !$login_data_changed) {
      $config
        ->set('classifications', $form_state->getValue('classifications'))
        ->set('avoid_classifications', $form_state->getValue('avoid_classifications'))
        ->save();
    }
    else {
      $config
        ->set('classifications', [])
        ->set('avoid_classifications', FALSE)
        ->save();
    }

    if ($login_data_changed || $preview_language_changed || $cache_changed) {
      $this->THRONApi->resetCache();
    }
  }

  /**
   * Tests connection with the THRON API.
   *
   * @return bool
   *   Whether communication was successfully established.
   */
  protected function testApiConnection($clientId, $appId, $appKey) {
    try {
      $this->THRONApi->loginApp([
        'client_id' => $clientId,
        'app_id' => $appId,
        'app_key' => $appKey,
      ]);
    } catch (\Exception $exception) {
      return FALSE;
    }
    return TRUE;
  }

}
