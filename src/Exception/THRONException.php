<?php

namespace Drupal\thron\Exception;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base exception class for THRON.
 */
abstract class THRONException extends \Exception {

  use LoggerChannelTrait;
  use MessengerTrait;
  use StringTranslationTrait;


  /**
   * Admin permission related to this exception.
   *
   * @var string
   */
  protected $adminPermission = 'administer thron configuration';

  /**
   * Message level to be used when displaying the message to the user.
   *
   * @var string
   */
  protected $messageLevel = 'error';

  /**
   * User-facing for admin users.
   *
   * @var string
   */
  protected $adminMessage;

  /**
   * Message to be logged in the Drupal's log.
   *
   * @var string
   */
  protected $logMessage;

  /**
   * Arguments for the log message.
   *
   * @var array
   */
  protected $logMessageArgs;

  /**
   * Constructs BundleNotExistException.
   *
   * @param string $message
   *   The message to print.
   * @param null $admin_message
   *   (optional) The admin message, if present.
   * @param null $log_message
   *   (optional) The log message, if present.
   * @param array $log_message_args
   *   Arguments for the message.
   */
  public function __construct($message, $admin_message = NULL, $log_message = NULL, array $log_message_args = []) {
    parent::__construct($message);
    $this->adminMessage = $admin_message ?: $message;
    $this->logMessage = $log_message ?: $this->adminMessage;
    $this->logMessageArgs = $log_message_args;
  }

  /**
   * Displays message to the user.
   */
  public function displayMessage() {
    if (\Drupal::currentUser()->hasPermission($this->adminPermission)) {
      $this->messenger()->addMessage($this->adminMessage, $this->messageLevel);
    }
    else {
      $this->messenger()->addMessage($this->getMessage(), $this->messageLevel);
    }
  }

  /**
   * Logs exception into Drupal's log.
   *
   * @return \Drupal\thron\Exception\THRONException
   *   This exception.
   */
  public function logException() {
    $this->getLogger('thron')->error($this->logMessage, $this->logMessageArgs);
    return $this;
  }

}
