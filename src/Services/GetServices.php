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
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Url;

/**
 * Class WatchCartoons.
 *
 * @package Drupal\nettv
 */
class GetServices {

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


  /**
  * Function to get list of fields of particular content type.
  *
  * @param string $content_type
  *   Machine name of content type.
  *
  * @return array
  *   field_info_instance of particular content type.
  */

  public function snp_get_field_list($entity_type = 'node', $content_type = '') {

    if (!empty($content_type)) {
        //$field_instance = field_info_instances("node", $content_type);
        $entityManager = \Drupal::service('entity_field.manager');
        $fields = $entityManager->getFieldDefinitions($entity_type, $content_type);
        \Drupal::logger('simple_node_importer')->notice("hello");
        /*$extra_fields = field_info_extra_fields('node', $content_type, 'form');

        if (array_key_exists('title', $extra_fields)) {
          $extra_fields['title']['required'] = TRUE;
          $field_instance = array('title' => $extra_fields['title']) + $field_instance;
        }*/
        return $fields;
        // return $field_instance;
    }
    else {
      return "";
    }
  }
}
