<?php

/**
 * @file
 * Contains Drupal\simple_node_importer\GetContentTypes.
 *
 * This class is tied into Drupal's config, but it doesn't have to be.
 *
 */

namespace Drupal\simple_node_importer\Services;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Url;

/**
 * Class WatchCartoons.
 *
 * @package Drupal\nettv
 */
class GetContentTypes {

  /**
   * Drupal\Core\Config\ConfigFactory definition.
   *
   * @var Drupal\Core\Config\ConfigFactory
   */
  protected $config_factory;
  /**
   * Constructor.
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->config_factory = $config_factory;
  }
  
  /**
   * In this method we are using the Drupal config service to
   * load the variables. Similar to Drupal 7 variable_get().
   * It also uses the new l() function and the Url object from core.
   * At the end of the day, we are just returning a string.
   * This could be refactored to use a Twig template in a future tutorial.
   */
  public function getContentTypeList() {
  	$nodeTypes = \Drupal\node\Entity\NodeType::loadMultiple();
    foreach ($nodeTypes as $key => $value) {
      $content_types[$key] = $value->get('name');
    }

    if (isset($content_types['simple_node'])){
      unset($content_types['simple_node']);      
    }

    return $content_types;
  }

  public function checkAvailablity($nodeType = 'simple_node'){
    $nodeTypes = \Drupal\node\Entity\NodeType::loadMultiple();
    foreach ($nodeTypes as $key => $value) {
      $content_types[$key] = $value->get('name');
    }

    if (isset($content_types['simple_node'])){
      return TRUE;      
    }
    else{
      return FALSE;      
    }

  }
}
