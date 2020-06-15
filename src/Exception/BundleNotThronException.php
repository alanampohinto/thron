<?php

namespace Drupal\thron\Exception;

use Drupal\Core\Url;

/**
 * Exception indicating that the bundle does not represent TGHRON assets.
 */
class BundleNotThronException extends THRONException {

  /**
   * {@inheritdoc}
   */
  protected $adminPermission = 'administer entity browsers';

  /**
   * Constructs BundleNotTHRONException.
   *
   * @param string $bundle
   *   Name of the bundle.
   */
  public function __construct($bundle) {
    $log_message = 'Media type @bundle is not using THRON plugin. Please fix the THRON <a href=":eb_conf">search widget configuration</a>.';
    $log_message_args = [
      ':eb_conf' => Url::fromRoute('entity.entity_browser.collection')
        ->toString(),
      '@bundle' => $bundle,
    ];
    $admin_message = $this->t('Media type @bundle is not using THRON plugin. Please fix the THRON <a href=":eb_conf">search widget configuration</a>.', $log_message_args);
    $message = $this->t(
      'THRON integration is not configured correctly. Please contact the site administrator.'
    );
    parent::__construct(
      $message,
      $admin_message,
      $log_message,
      $log_message_args
    );
  }

}
