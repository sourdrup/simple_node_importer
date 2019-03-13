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
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\file\Entity\File;

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
  public function snp_select_create_csv($content_type) {
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
  public function create_simple_node_import_table($form) {
    // Table header information.
    $form = $form['mapping_form'];
      $tableheader = array(
        array('data' => t('Content type Field(s)')),
        array('data' => t('CSV Column(s)')),
      );
      // A variable to hold the row information for each table row.
      $rows = array();
      foreach (\Drupal\Core\Render\Element::children($form) as $element_key) {
        $title = '';
        // Hide field labels.
        $form[$element_key]['#title_display'] = 'invisible';
        if (isset($form[$element_key]['#title'])) {
          $title = ($form[$element_key]['#required']) ? $form[$element_key]['#title'] . '<span class="form-required" title="This field is required.">*</span>' : $form['form'][$element_key]['#title'];
        }
        $rows[] = array(
          'data' => array(
            array(
              'data' => $title,
              'class' => 'field-title',
            ),
            array(
              'data' => render($form[$element_key]),
              'class' => 'field-value',
            ),
          ),
        );
      }
    return $tableparameters = array($rows,$tableheader);
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
    $defaultFieldArr = ['title', 'body', 'uid'];
    $haystack = 'field_';
      foreach ($fieldsManager as $key  => $field ){
        if(in_array($key, $defaultFieldArr) || strpos($key, $haystack) !== FALSE){
          if($type == 'csv'){
            if(method_exists ($field->getLabel() , 'render')){
              $fieldsArr[$key] = $field->getLabel()->render();          
            }
            else{
              $fieldsArr[$key] = $field->getLabel();
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

  /**
  * Checks the widget type of each field.
  */
  public function checkFieldWidget($field_names, $data, $node, $entity_type) {
   
    $excludeFieldArr = ['type', 'nid', 'uid', 'title'];
    $flag = TRUE;
    foreach ($field_names as $field_machine_name) {
      if(!in_array($field_machine_name, $excludeFieldArr)){
        $field_info = FieldStorageConfig::loadByName($entity_type, $field_machine_name);
        $entityManager = \Drupal::service('entity_field.manager');
        $field_definition = $entityManager->getFieldDefinitions($entity_type, $data['type']);
        $fieldStorageDefinition = $entityManager->getFieldStorageDefinitions($entity_type, $data['type']);
        
        if($field_machine_name == 'field_tags'){
          $key = key($field_definition[$field_machine_name]->getSetting('handler_settings')['target_bundles']);
        }
        
        $fieldProperties = $field_definition[$field_machine_name];
        $fieldLabel = $field_info->getLabel();
        $fieldType = $field_info->getType();
        $fieldTypeProvider = $field_info->getTypeProvider();
        $fieldCardinality = $field_info->getCardinality();
        $fieldIsRequired = $fieldProperties->isRequired();
      
        if($fieldType == 'entity_reference'){
          $fieldSetting = $field_info->getSetting('target_type');
        }
        else if($fieldType == 'datetime'){
          $fieldSetting = $field_info->getSetting('datetime_type');
        }
        else{
          $fieldSetting = NULL;
        }

        $dataValidated = $this->getFieldValidation($fieldType, $data[$field_machine_name], $fieldIsRequired);

        if ($dataValidated){

          switch ($fieldType) {
            case 'email':
              $node[$field_machine_name] = $this->buildNodeData($data[$field_machine_name], $fieldType);
              break;

            case 'image':
            case 'file':
              $node[$field_machine_name] = $this->buildNodeData($data[$field_machine_name], $fieldType);
              break;
            case 'entity_reference':
              $preparedData = $this->prepareEntityReferenceFieldData($field_definition, $field_machine_name, $data, $node, $fieldSetting);
              if(!$preparedData){
                $flag = FALSE;
                break;
              }else{
                $node[$field_machine_name] = $preparedData;
              } 
              break;

            case 'text':
            case 'string':
            case 'text_long':
            case 'text_with_summary':
              $node[$field_machine_name] = $this->buildNodeData($data[$field_machine_name], $fieldType);
              break;

            case 'boolean':
              $node[$field_machine_name] = ($data[$field_machine_name] == 1) ? $data[$field_machine_name] : ((strtolower($data[$field_machine_name]) == 'y') ? 1 : 0);
              break;

            case 'datetime':
              $node[$field_machine_name] = $this->buildNodeData($data[$field_machine_name], $fieldType, $fieldSetting);
              break;

            case 'number_integer':
            case 'number_float':
            case 'link':
              $node[$field_machine_name] = $this->buildNodeData($data[$field_machine_name], $fieldType, $fieldSetting);
              break;

            case 'list_text':
            case 'list_string':
            case 'list_float':
            case 'list_integer':
              $allowed_values = options_allowed_values($fieldStorageDefinition[$field_machine_name]);
              if (is_array($data[$field_machine_name])) {
                foreach ($data[$field_machine_name] as $k => $field_value) {
                  $key_value = array_search($field_value, $allowed_values, TRUE);
                  if($key_value){
                    $node[$field_machine_name][$k]['value'] = $key_value;
                  }
                  else{
                    $flag = FALSE;
                    break;
                  }
                }
              }
              else {
                $key_value = array_search(strtolower($data[$field_machine_name]), array_map('strtolower', $allowed_values));
                if($key_value){
                  $node[$field_machine_name][0]['value'] = $key_value;
                }
                else{
                  $flag = FALSE;
                  break;
                }
              }              
              break;
          }// end of switch case

        }
        else{
          $node['result'] = $data;
          break;
        }        
      }// end of 1st if   
    }// end of foreach

    if($flag === FALSE){
      $node = array();
      $node['result'] = $data;
      return $node;
    }
    else{
      return $node;
    }
  }

  public function getUserByEmail(string $email){
    //load user object
    $userObj = user_load_by_mail($email);
    if($userObj){
      return $userObj;
    }
    else{
      return $this->createUserByEmail($email);
    }

  }

  public function createUserByEmail(string $email){

    $username = explode('@', $email);

    $uname = $this->getUserByUsername($username[0]);

    $user = User::create([

      'name' => $uname,    

      'mail' => $email,

      'pass' => user_password($length = 10),

      'status' => 1,

      'roles'  => ['authenticated']

    ]);

    return $user->save();    
  }

  public function getUserByUsername(string $uname){
    $uids = \Drupal::entityQuery('user')
        ->condition('name', $uname)
        ->range(0, 1)
        ->execute();

    $today = date('dmy');
    if(!empty($uids)){
      return $uname.$today;
    }
    else{
      return $uname;
    }
  }

  public function prepareEntityReferenceFieldData($field_definition, $field_machine_name, $data, $node, $fieldSetting) {

    $handler = $field_definition[$field_machine_name]->getSetting('handler');
    $flag = TRUE;

    if($fieldSetting == 'taxonomy_term'){
      $handler_settings = $field_definition[$field_machine_name]->getSetting('handler_settings');
      $target_bundles = $handler_settings['target_bundles'];
      $vocabulary_name = is_array($target_bundles) ? $target_bundles : key($target_bundles);
      $allw_term = \Drupal::config('simple_node_importer.settings')->get('simple_node_importer_allow_add_term');    
      // code for taxonomy data handling
      if((is_array($vocabulary_name) && count($vocabulary_name) > 1) || empty($data[$field_machine_name])){
        return $flag = FALSE;
      }
      else {
        if (is_array($data[$field_machine_name])) {
          foreach ($data[$field_machine_name] as $k => $term_name) {
            $termArray = [
              'name' => $term_name,
              'vid' => $vocabulary_name
            ];

            $taxos_obj = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties($termArray);
            $termKey = key($taxos_obj);
         
            if (!$taxos_obj && $allw_term) {

              $term = \Drupal\taxonomy\Entity\Term::create([
                  'vid' => $vocabulary_name,
                  'name' => $term_name,
              ]);

              $term->enforceIsNew();
              $term->save();

              $dataRow[$k]['target_id'] = $term->id();
            }
            else {
              $dataRow[$k]['target_id'] = $taxos_obj[$termKey]->id();
            }
          }
        }
        else {

          $termArray = [
              'name' => $data[$field_machine_name],
              'vid' => $vocabulary_name
            ];
        
          $taxos_obj = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties($termArray);
          $termKey = key($taxos_obj);
          if (!$taxos_obj && $allw_term) {
            $term = \Drupal\taxonomy\Entity\Term::create([
                'vid' => $vocabulary_name,
                'name' => $term_name,
            ]);

            $term->enforceIsNew();
            $term->save();
            $dataRow[0]['target_id'] = $term->id();
          }
          else {
            $dataRow[0]['target_id'] = $taxos_obj[$termKey]->id();
          }
        }
      }
      return $dataRow;
    }

    if ($fieldSetting == 'user' && !empty($data[$field_machine_name])){
      $userEmail = $data[$field_machine_name];
      if(is_array($userEmail)){
        foreach($userEmail as $email){
          $flag = $this->getFieldValidation('email', $email);
        }
        if($flag){
          foreach($userEmail as $email){
            $user = $this->getUserByEmail($email);
            if($user && !is_integer($user)){
              $dataRow[] = $user->id();
            }
            else{
              $dataRow[] = $user;
            }
          }
        }
        else{
          return $flag = FALSE;
        }
      }
      else{
        $flag = $this->getFieldValidation('email', $userEmail);
        if($flag){          
          $user = $this->getUserByEmail($userEmail);
          if($user && !is_integer($user)){
            $dataRow = $user->id();
          }
          else{
            $dataRow = $user;
          }        
        }
        else{
          return $flag = FALSE;
        }
      }
    }
    else{
      return $flag = FALSE;
    }
    return $dataRow;
  }

  public function getFieldValidation($fieldType, $field_data, $fieldIsRequired = FALSE) {

    $flag = TRUE;

    if ($field_data == '' && $fieldIsRequired == TRUE) {
      return $flag = FALSE;
    }
    else if (!empty($field_data)){
      switch($fieldType) {
        case 'email':
          if (is_array($field_data)){
            foreach($field_data as $fieldData){
              $flag = (!empty($fieldData) && !filter_var($fieldData, FILTER_VALIDATE_EMAIL)) ? FALSE : TRUE;
            }
          }
          else{
            $flag = (!empty($field_data) && !filter_var($field_data, FILTER_VALIDATE_EMAIL)) ? FALSE : TRUE;
          }
          break;
        case 'image':
        case 'link':
          if (is_array($field_data)){
            foreach($field_data as $fieldData){
              $flag = (!empty($fieldData) && !filter_var($fieldData, FILTER_VALIDATE_URL)) ? FALSE : TRUE;
            }
          }
          else{
            $flag = (!empty($field_data) && !filter_var($field_data, FILTER_VALIDATE_URL)) ? FALSE : TRUE;
          }
          break;
      }
    }
    

    return $flag;
  }

  public function buildNodeData($data, $fieldType, $fieldSetting = NULL){
    $i = 0;
    $fieldTypes = ['number_integer', 'number_float'];
    $dataRow = [];

    if (is_array($data) && !empty($data)) {
      foreach ($data as $value) {
        if(in_array($fieldType, ['image','file'])){
          // code for image/file field..
          $file = system_retrieve_file($value, NULL, TRUE, FILE_EXISTS_REPLACE);
          $dataRow[$i]['target_id'] = !empty($file) ? $file->id() : NULL;
        }
        else if($fieldType == 'datetime'){
          $dataRow[$i]['value'] = ($fieldSetting == 'datetime') ? date_format(date_create($value), 'Y-m-d\TH:i:s') : date_format(date_create($value), 'Y/m/d');
        }
        else if(in_array($fieldType, $fieldTypes)){
          $dataRow[$i]['value'] = $value;
        }
        else if($fieldType == 'link'){
          $dataRow[$i]['uri'] = $data;
        }
        else{
          // code...
          $dataRow[$i] = trim($value);
        }        
        $i++;
      }
    }
    else if(!empty($data)) {
      if(in_array($fieldType, ['image','file'])){
        // code for image/file field..
        $file = system_retrieve_file($data, NULL, TRUE, FILE_EXISTS_REPLACE);
        $dataRow[0]['target_id'] = !empty($file) ? $file->id() : NULL;
      }
      else if($fieldType == 'datetime'){
        $dataRow['value'] = ($fieldSetting == 'datetime') ? date_format(date_create($data), 'Y-m-d\TH:i:s') : date_format(date_create($data), 'Y-m-d');
      }
      else if(in_array($fieldType, $fieldTypes)){
        $dataRow[0]['value'] = $data;
      }
      else if($fieldType == 'link'){
        $dataRow['uri'] = $data;
      }
      else{
        // code...
        $dataRow = trim($data);
      }        
    }
    return $dataRow;
  }

}
