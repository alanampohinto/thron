<?php

namespace Drupal\thron\Exception;

/**
 * Exception indicating that the usage can't be added for the THRON asset.
 */
class UnableToAddUsageException extends THRONException {

  /**
   * Constructs UnableToAddUsageException.
   */
  public function __construct($original_message) {
    $log_message = 'Unable to add usage for THRON asset: @message';
    $log_message_args = ['@message' => $original_message];
    $message = $this->t(
      'Unable to add usage for THRON asset. Please see the logs for more information.'
    );

    parent::__construct(
      $message,
      NULL,
      $log_message,
      $log_message_args
    );
  }

}
