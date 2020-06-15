<?php

namespace Drupal\thron\Exception;

use Drupal\Core\Url;

/**
 * Exception indicating that the given credentials couldn't not be used with this module.
 */
class NoCredentialException extends THRONException {

  /**
   * Constructs UnableToConnectException.
   */
  public function __construct() {
    $log_message = 'No credentials found to connect to THRON. ';
    $log_message .= 'Check if the  <a target="_blank" href=":url">configuration is set properly</a> ';
    $log_message .= 'or contact <a href=":support">support</a>.';
    $log_message_args = [
      ':url' => Url::fromRoute('thron.configuration_form')->toString(),
      ':support' => 'https://www.thron.com/en/customer-service',
    ];
    $admin_message = $this->t($log_message, $log_message_args);
    $message = $this->t(
      'No credentials stored in config. Please contact the site administrator or THRON support.'
    );
    parent::__construct(
      $message,
      $admin_message,
      $log_message,
      $log_message_args
    );
  }

}
