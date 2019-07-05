<?php

namespace Drupal\thron\Exception;

use Drupal\Core\Url;

/**
 * Exception indicating that the Pkey is not present in login data.
 */
class NoPkeyException extends ThronException {

  /**
   * Constructs NoPkeyException.
   *
   * @param string $bundle
   *   Name of the bundle.
   */
  public function __construct() {
    $log_message = 'Pkey not present. Check if the  <a target="_blank" href=":url">configuration is set properly</a> or contact <a href=":support">support</a>.';
    $log_message_args = [
      ':url' => Url::fromRoute('thron.configuration_form')->toString(),
      ':support' => 'https://www.thron.com/en/customer-service',
    ];
    $admin_message = $this->t($log_message, $log_message_args);
    $message = $this->t(
      'Unable to fetch a pKey. Please contact the site administrator or THRON support.'
    );
    parent::__construct(
      $message,
      $admin_message,
      $log_message,
      $log_message_args
    );
  }

}
