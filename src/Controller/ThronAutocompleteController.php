<?php

namespace Drupal\thron\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityAutocompleteMatcher;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\example\ExampleInterface;
use Drupal\thron\THRONApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for THRON routes.
 */
class ThronAutocompleteController extends ControllerBase {

  /**
   * The thron_api service.
   *
   * @var \Drupal\thron\THRONApiInterface
   */
  protected $thronApi;

  /**
   * The key value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * The controller constructor.
   *
   * @param \Drupal\thron\THRONApiInterface $thron_api
   *   The thron_api service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value factory.
   */
  public function __construct(THRONApiInterface $thron_api, KeyValueFactoryInterface $key_value_factory) {
    $this->thronApi = $thron_api;
    $this->keyValueFactory = $key_value_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('thron_api'),
      $container->get('keyvalue')
    );
  }

  /**
   * Builds the response.
   */
  public function handleTagsAutocomplete(Request $request, $depth, $classifications) {
    $matches = [];
    $search_key = $request->query->get('q');
    foreach (explode("+", $classifications) as $classification_id) {
      $retrieved_tags = $this->thronApi->getTagsListByClassification($classification_id, [], $depth, $search_key);
      foreach ($retrieved_tags as $key => $tag) {
        $matches[] = [
          'value' => [
            'id' => $tag['id'],
            'name' => $tag['name'],
            'classificationId' => $classification_id,
          ],
          'label' => $tag['name'],
        ];
      }
    }
    return new JsonResponse($matches);
  }

}
