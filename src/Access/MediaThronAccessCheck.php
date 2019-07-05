<?php

namespace Drupal\thron\Access;


use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessCheck;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\Routing\Route;

/**
 * Class MediaThronAccessCheck
 *
 * @package Drupal\thron\Access
 */
class MediaThronAccessCheck extends EntityAccessCheck implements AccessInterface {

  /**
   * The key used by the routing requirement.
   *
   * @var string
   */
  public static $requirementsKey = '_thron_media_access';

  /**
   * @param \Symfony\Component\Routing\Route $route
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    // Split the entity type and the operation.
    $requirement = $route->getRequirement($this::$requirementsKey);
    list($entity_type, $operation) = explode('.', $requirement);
    // If $entity_type parameter is a valid entity, call its own access check.
    $parameters = $route_match->getParameters();
    if ($parameters->has($entity_type)) {
      $entity = $parameters->get($entity_type);
      if ($entity instanceof MediaInterface) {
        if ($entity->bundle() == 'thron_with_media_source') {
          return AccessResult::forbidden();
        }
      }
    }
    // No opinion, so parent access check should decide if access should be
    // allowed or not.
    return parent::access($route, $route_match, $account);
  }

}