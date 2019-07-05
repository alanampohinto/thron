<?php

namespace Drupal\thron\Access;


use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityCreateAccessCheck;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Class MediaThronAddAccessCheck
 *
 * @package Drupal\thron\Access
 */
class MediaThronAddAccessCheck extends EntityCreateAccessCheck implements AccessInterface {

  /**
   * The key used by the routing requirement.
   *
   * @var string
   */
  public static $thronRequirementsKey = '_thron_media_add_access';

  /**
   * @param \Symfony\Component\Routing\Route $route
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return bool|\Drupal\Core\Access\AccessResultForbidden|\Drupal\Core\Access\AccessResultInterface|\Drupal\Core\Access\AccessResultNeutral
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    list($entity_type, $bundle) = explode(':', $route->getRequirement($this::$thronRequirementsKey));

    if ($entity_type == 'media' && $bundle && strpos($bundle, '{') !== FALSE) {
      $bundle = preg_replace('/{(\w+)}/', '${1}', $bundle);
      $parameters = $route_match->getParameters();
      if ($parameters->has($bundle)) {
        $media_type = $parameters->get($bundle);
        if ($media_type->id() == 'thron_with_media_source') {
          return AccessResult::forbidden();
        }
      }
    }

    // No opinion, so parent access check should decide if access should be
    // allowed or not.
    return parent::access($route, $route_match, $account);
  }

}