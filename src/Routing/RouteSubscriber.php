<?php

namespace Drupal\thron\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\thron\Access\MediaThronAccessCheck;
use Drupal\thron\Access\MediaThronAddAccessCheck;
use Symfony\Component\Routing\RouteCollection;

/**
 * The route subscriber
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Alters existing routes for a specific collection.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection for adding routes.
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.media.add_form')) {
      $route->setRequirement(MediaThronAddAccessCheck::$thronRequirementsKey, 'media:{media_type}');
    }

    if ($route = $collection->get('entity.media.canonical')) {
      $route->setRequirement(MediaThronAccessCheck::$requirementsKey, 'media.view');
    }

    if ($route = $collection->get('entity.media.delete_form')) {
      $route->setRequirement(MediaThronAccessCheck::$requirementsKey, 'media.delete');
    }

    if ($route = $collection->get('entity.media.edit_form')) {
      $route->setRequirement(MediaThronAccessCheck::$requirementsKey, 'media.edit');
    }

    // We override the default EntityEmbedDialog form for some customizations:
    if ($route = $collection->get('entity_embed.dialog')) {
      $route->setDefault('_form', '\Drupal\thron\Form\EntityEmbedDialogOverride');
    }
  }
  
}
