<?php

namespace Drupal\thron\Exception;

use Drupal\Core\Url;

/**
 * Exception indicating that an attempt to connect to Thron failed.
 */
class UnableToConnectException extends THRONException {

  /**
   * Constructs UnableToConnectException.
   */
  public function __construct() {
    $log_message = 'Unable to connect to THRON. Check if the  <a target="_blank" href=":url">configuration is set properly</a> or contact <a href=":support">support</a>.';
    $log_message_args = [
      ':url' => Url::fromRoute('thron.configuration_form')->toString(),
      ':support' => 'https://www.thron.com/en/customer-service',
    ];
    $admin_message = $this->t($log_message, $log_message_args);
    $message = $this->t(
      'Unable to connect to THRON. Please contact the site administrator.'
    );
    parent::__construct(
      $message,
      $admin_message,
      $log_message,
      $log_message_args
    );
  }

}
