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
        
        $entityManager = \Drupal::service('entity_field.manager');
        $fieldsManager = $entityManager->getFieldDefinitions($entity_type, $content_type);
        $defaultFieldArr = ['title', 'body'];
        $haystack = 'field_';

        foreach ($fieldsManager as $key => $field){
          if(in_array($key, $defaultFieldArr) || strpos($key, $haystack) !== FALSE){
              
            //fetch the list of required fields.
            if($fieldsManager[$key]->isRequired()){
              $fieldsArr['required'][$key] = $key;
            }

            //fetch the list of multivalued fields.
            if (!in_array($key, $defaultFieldArr)){
              $FieldStorageConfig = \Drupal\field\Entity\FieldStorageConfig::loadByName($entity_type, $key);
              if($FieldStorageConfig->getCardinality() === -1 || $FieldStorageConfig->getCardinality() > 1){
                $fieldsArr['multivalued'][$key] = $key;
              }
            }              
          }
        }
       
       // log the list of required and multivalued fields  
        \Drupal::logger('simple_node_importer')->notice('<pre><code>' . print_r($fieldsArr, TRUE) . '</code></pre>');

        // return list of fields;
        return $fieldsArr;
    }
    else {
      return "";
    }
  }
}
