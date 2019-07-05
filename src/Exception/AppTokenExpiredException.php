<?php

namespace Drupal\thron\Exception;


use Drupal\Core\Url;

class AppTokenExpiredException extends ThronException {

  /**
   * Constructs UnableToConnectException.
   */
  public function __construct() {
    $log_message = 'Application token expired. ';
    $log_message .= 'Check if the  <a target="_blank" href=":url">configuration is set properly</a> or contact <a href=":support">support</a>.';
    $log_message_args = [
      ':url' => Url::fromRoute('thron.configuration_form')->toString(),
      ':support' => 'https://www.thron.com/en/customer-service',
    ];
    $admin_message = $this->t($log_message, $log_message_args);
    $message = $this->t(
      'Can\'t login into application. Application token expired and the renew attempt failed. Please contact the site administrator or THRON support.'
    );
    parent::__construct(
      $message,
      $admin_message,
      $log_message,
      $log_message_args
    );
  }
}