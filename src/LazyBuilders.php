<?php

namespace Drupal\thron;

use Drupal\Core\Render\RendererInterface;

/**
 * Provides #lazy_builder callbacks.
 */
class LazyBuilders {

  /**
   * The renderer service
   *
   * @var \Drupal\thron\THRONApiInterface
   */
  protected $api;

  /**
   * The renderer service
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new CartLazyBuilders object.
   *
   * @param \Drupal\thron\THRONApiInterface $thron_api
   *   The renderer service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(THRONApiInterface $thron_api, RendererInterface $renderer) {
    $this->api = $thron_api;
    $this->renderer = $renderer;
  }
}
