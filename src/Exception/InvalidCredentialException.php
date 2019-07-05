<?php

namespace Drupal\thron\Exception;

use Drupal\Core\Url;

/**
 * Exception indicating that the given credentials couldn't not be used with this module.
 */
class InvalidCredentialException extends ThronException {

  /**
   * Constructs UnableToConnectException.
   *
   * @param string $type
   */
  public function __construct($type) {
    $log_message = 'Given credentials could not be used to connect to THRON (invalid @type)';
    $log_message .= 'Check if the  <a target="_blank" href=":url">configuration is set properly</a> or contact <a href=":support">support</a>.';
    $log_message_args = [
      ':url' => Url::fromRoute('thron.configuration_form')->toString(),
      ':support' => 'https://www.thron.com/en/customer-service',
      '@type' => $type,
    ];
    $admin_message = $this->t($log_message, $log_message_args);
    $message = $this->t(
      'Invalid credentials given. Please contact the site administrator or THRON support.'
    );
    parent::__construct(
      $message,
      $admin_message,
      $log_message,
      $log_message_args
    );
  }

}
