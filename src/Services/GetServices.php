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
  function snp_select_create_csv($content_type) {
    $csv = array();
    $type = 'csv';
    $labelarray = $this->snp_get_field_list($entity_type = 'node',$content_type, $type);
    foreach ($labelarray as $key => $value) {
     $csv[] =  $value;
    }
    $filename = $content_type . '_template.csv';
  
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header('Content-Description: File Transfer');
    header("Content-type: text/csv");
    header("Content-Disposition: attachment; filename={$filename}");
    header("Expires: 0");
    header("Pragma: public");
     $fh = @fopen('php://output', 'w');
  
    // Put the data into the stream.
    fputcsv($fh, $csv);
    fclose($fh);
    // Make sure nothing else is sent, our file is done.
    exit;
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

  public function simple_node_importer_getallcolumnheaders($fileuri) {
    $handle = fopen($fileuri, 'r');
    $row = fgetcsv($handle);
    foreach ($row as $value) {
      // code...
      $key = strtolower(preg_replace('/\s+/', '_', $value));
      $column[$key] = $value;
    }
    return $column;
  }
  
  public function snp_get_field_list($entity_type = 'node', $content_type = '', $type = NULL) {

    if (!empty($content_type)) {

      $fieldsManager = $this->snp_get_fields_definition($entity_type, $content_type);
      
      $fieldsArr = $this->snp_getFields($fieldsManager, $type, $entity_type);

      return $fieldsArr;
    }
    else {
      return "";
    }
  }

  public function simple_node_importer_getpreselectedvalues($form, $headers) {
    foreach ($form['mapping_form'] as $field => $attributes) {
      if(is_array($attributes)){
        foreach($attributes['#options'] as $key => $value){
          if(array_key_exists($key, $headers) && $headers[$key] == $attributes['#title']){
            $form['mapping_form'][$field]['#default_value'] = $key;
          }
        }
      }
    }
    return $form;
  }

  public function snp_get_fields_definition($entity_type = 'node', $content_type = ''){
    $entityManager = \Drupal::service('entity_field.manager');
    $fieldsManager = $entityManager->getFieldDefinitions($entity_type, $content_type);
    return $fieldsManager;
  }

  public function snp_getFields($fieldsManager, $type, $entity_type = NULL){
    $defaultFieldArr = ['title', 'body'];
    $haystack = 'field_';
      foreach ($fieldsManager as $key  => $field ){
        if(in_array($key, $defaultFieldArr) || strpos($key, $haystack) !== FALSE){
          if($type == 'csv'){
            if($key == 'title'){
              $fieldsArr[$key] = $field->getLabel()->render();          
            }
            else{
              $fieldsArr[$key] = $field->getLabel() ;
            }
          }
          else if($type == 'import'){
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
          else if($type == 'mapping'){
            $fieldsArr[$key] = $field;
          }
        }
      }
    return $fieldsArr;
  }
}
