<?php

namespace Drupal\thron\Exception;

/**
 * Exception indicating that the usage can't be deleted for the THRON asset.
 */
class UnableToDeleteUsageException extends ThronException {

  /**
   * Constructs UnableToDeleteUsageException.
   */
  public function __construct($original_message) {
    $log_message = 'Unable to delete usage: @message';
    $log_message_args = ['@message' => $original_message];
    $message = $this->t(
      'Unable to delete usage from THRON asset. Please see the logs for more information.'
    );

    parent::__construct(
      $message,
      NULL,
      $log_message,
      $log_message_args
    );
  }
}
