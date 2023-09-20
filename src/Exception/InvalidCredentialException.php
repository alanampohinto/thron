<?php

namespace Drupal\thron\Exception;

use Drupal\Core\Url;

/**
 * Exception credentials couldn't not be used with this module.
 */
class InvalidCredentialException extends THRONException {

  /**
   * Constructs UnableToConnectException.
   *
   * @param string $type
   *   Message that was originally thrown from the invalid credential.
   */
  public function __construct($type, $value = '') {
    $log_message = 'Given credentials could not be used to connect to THRON (invalid @type: @value)';
    $log_message .= '<br>Check if the  <a target="_blank" href=":url">configuration is set properly</a> or contact <a href=":support">support</a>.';
    $log_message_args = [
      ':url' => Url::fromRoute('thron.configuration_form')->toString(),
      ':support' => 'https://www.thron.com/en/customer-service',
      '@type' => $type,
      '@value' => $value,
    ];
    $admin_message = $this->t('Given credentials could not be used to connect to THRON (invalid @type: @value)', $log_message_args);
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
