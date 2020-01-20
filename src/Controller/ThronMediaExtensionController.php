<?php

namespace Drupal\thron\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\thron\THRONApiInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for THRON routes.
 */
class ThronMediaExtensionController extends ControllerBase {

  /**
   * The thron_api service.
   *
   * @var \Drupal\thron\THRONApiInterface
   */
  protected $api;

  /**
   * The controller constructor.
   *
   * @param \Drupal\thron\THRONApiInterface $thron_api
   *   The thron_api service.
   */
  public function __construct(THRONApiInterface $thron_api) {
    $this->api = $thron_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('thron_api'));
  }

  /**
   * Builds the response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param $media_id
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *
   * @throws \Exception
   */
  public function get(Request $request, $media_id) {
    $content = '?';
    $media_info = ($media_id != 'NOMEDIA') ? $this->api->getMediaDetails($media_id, 'source') : NULL;

    if (!empty($media_info)) {
      $first = reset($media_info);
	  if($first['extension'] && trim($first['extension']) != '') {
		  $extension = $first['extension'];
		  $content = [
			'#plain_text' => strtoupper($extension),
		  ];
	  } else {
		  $content = [
			'#plain_text' => "",
		  ];
	  }
    }

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("span[thron-media-id='$media_id']", $content));
    return $response;
  }

}
