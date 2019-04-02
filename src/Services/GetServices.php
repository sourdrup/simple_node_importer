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
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
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
  
  public function snp_select_create_csv($entity_type, $content_type) {
    $csv = array();
    $type = 'csv';
    if($entity_type == 'taxonomy'){
      $csv = ['Vocabolary','Term1','Term2','Term3','Term4'];
      $filename = $entity_type . '_template.csv';
    }
    else{
      $labelarray = $this->snp_get_field_list($entity_type,$content_type, $type);
      foreach ($labelarray as $key => $value) {
        $csv[] =  $value;
      }
      $filename = $content_type . '_template.csv';
    }

  
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

  public function simple_node_importer_createTaxonomy($nid){
    $node = Node::load($nid);
    $fid = $node->get('field_upload_csv')->getValue()[0]['target_id'];
    $file = \Drupal\file\Entity\File::load($fid);
    $uri = $file->getFileUri();
    $url = \Drupal\Core\Url::fromUri(file_create_url($uri))->toString();
    $handle = fopen($url, 'r');
    while($row = fgetcsv($handle)){
      for($i = 0;$i <=sizeof($row)-1;$i++){
        $name = $row[$i];
        if(empty($name)){
          continue;
        }
        if($i == 0){
          $vid = strtolower(preg_replace('/\s+/', '_', $name));
          $vocabularies = \Drupal\taxonomy\Entity\Vocabulary::loadMultiple();
          if (!isset($vocabularies[$vid])){
            $vocabulary = \Drupal\taxonomy\Entity\Vocabulary::create(array(
              'vid' => $vid,
              'description' => '',
              'name' => $name,
              ));
              $vocabulary->save();
          }
        }
        else{
          $termArray = [
            'name' => $name,
            'vid' => $vid
            ];
          $termid = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties($termArray);
          if($i == 1){
            if(empty($termid)){
		          $term = Term::create($termArray)->save();
	          }             
          }
          else{
            $parent = $row[$i-1];
            $termArray = [
              'name' => $parent,
              'vid' => $vid
              ];
            $termexist = 0;
            $parenttermid = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties($termArray);
            $childterms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadChildren(key($parenttermid));
            foreach($childterms as $childterm){
              if($childterm->getName() == $name){
                  $termexist = 1;
              }
            }
            if($termexist == 0){
              if(!empty($parenttermid )) {
		            $term = Term::create(array(
                  'parent' => key($parenttermid),
                  'name' => $name,
                  'vid' => $vid,
                  ))->save();
	            }
            }
          }
        }
      }
    }
    drupal_set_message('Taxonomies are created successfully');
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
   if($entity_type == 'node'){
    $defaultFieldArr = ['title', 'body', 'status', 'uid'];
   }
    else{
    $defaultFieldArr = [ 'name', 'mail', 'status', 'roles', 'user_picture'];
    }
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

    $excludeFieldArr = ['name', 'mail','status', 'roles', 'nid','type', 'uid', 'title'];

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
          $flag = FALSE;
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

  public function getUserByEmail($email, $op = NULL){
    //load user object
    // $op could be 'new', 'admin', 'current', 'content_validate'
    $userObj = user_load_by_mail($email);

    if($userObj){
      return $userObj;
    }
    else if($op == 'new'){
      return $this->createNewUser($email);
    }
    else if($op == 'admin'){
      $adminUid = 1;
      return $adminUid;
    }
    else if($op == 'current'){
      $userObj = \Drupal::currentUser();
      return $userObj; 
    }
    else if($op == 'content_validate'){
      return NULL;
    }
    

  }

  public function createNewUser(string $email = NULL, string $uname = NULL){

    if(!empty($email)){
      $today = date('dmy');
      $username = explode('@', $email);
      $userId = $this->getUserByUsername($username[0]);
      if($userId && is_integer($userId)){
        $uname = $username.$today;
      }
      else{
        $uname = $username;
      }
    }
    else if(!empty($uname)){
      $email = '';
    }    

    $user = User::create([

      'name' => $uname,    

      'mail' => $email,

      'pass' => user_password($length = 10),

      'status' => 1,

      'roles'  => ['authenticated']

    ]);

    return $user->save();    
  }

  public function getUserByUsername(string $uname, $op = NULL){

    // $op could be 'new', 'admin', 'current', 'content_validate'
    $userId = \Drupal::entityQuery('user')
        ->condition('name', $uname)
        ->range(0, 1)
        ->execute();

    if(!empty($userId)){
      return key($userId);
    }
    else if($op == 'new'){
      return $this->createNewUser(NULL, $uname);
    }
    else if($op == 'admin'){
      $adminUid = 1;
      return $adminUid;
    }
    else if($op == 'current'){
      $userObj = \Drupal::currentUser();
      return $userObj; 
    }
    else if($op == 'content_validate'){
      return NULL;
    }
    else{
      return NULL;
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

    if ($fieldSetting == 'user'){
      $userEmail = $data[$field_machine_name];
      if(is_array($userEmail)){
        foreach($userEmail as $email){
          $flag = $this->getFieldValidation('email', $email);
          if($flag){
            $user = $this->getUserByEmail($email, 'content_validate');
            if($user && !is_integer($user)){
              $dataRow[] = $user->id();
            }
            else{
              return $flag = FALSE;
            }
          }else{
            $uid = $this->getUserByUsername($email, 'content_validate');
            if($uid){
              $dataRow[] = $uid;
            }
            else{
              return $flag = FALSE;
            }
          }
        }
      }
      else{
       $flag = $this->getFieldValidation('email', $userEmail);
        if($flag){
          $user = $this->getUserByEmail($userEmail, 'content_validate');
          if($user && !is_integer($user)){
            $dataRow = $user->id();
          }
          else{
            return $flag = FALSE;
          }
        }else{
          $uid = $this->getUserByUsername($userEmail, 'content_validate');
          if($uid){
            $dataRow = $uid;
          }
          else{
            return $flag = FALSE;
          }
        }
      }
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

  /**
   * Function to generate random strings.
   *
   * @param int $length
   *   Number of characters in the generated string.
   *
   * @return string
   *   A new string is created with random characters of the desired length.
   */
  public function generateReference($length = 10) {
    srand();
    $string = "";
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    for ($i = 0; $i < $length; $i++) {
      $string .= substr($chars, rand(0, strlen($chars)), 1);
    }
    return $string;
  }

  public static function getImageFID($FileUrl){
    // code for image/file field..
    $file = system_retrieve_file($FileUrl, NULL, TRUE, FILE_EXISTS_REPLACE);
    $fid = !empty($file) ? $file->id() : NULL;
    return $fid;
  }

  public static function generateFieldSetValue($fieldKey, $fieldVal, $fieldWidget, $entity_type, $bundle){

    $excludeFieldArr = ['type', 'nid', 'uid', 'title', 'reference', 'status', 'name', 'mail', 'roles'];
    $flag = TRUE;
    $key = 0;
    if(!in_array($fieldKey, $excludeFieldArr)){
      $getFieldInfo = \Drupal\simple_node_importer\Services\GetServices::getFieldInfo($entity_type, $fieldKey, $bundle);
      $fieldType = $getFieldInfo['fieldType'];
      $fieldIsRequired = $getFieldInfo['fieldIsRequired'];
      $fieldCardinality = $getFieldInfo['fieldCardinality'];

      if(empty($fieldVal) && $fieldIsRequired){
        $fields[] = $fieldKey;
      }

      switch ($fieldType) {
        case 'text_with_summary':
          # code...
          $fieldWidget[0]['#default_value'] = $fieldVal;
          break;
        
        case 'list_float':
        case 'list_integer':
        case 'list_string':
          if(!empty($fieldVal) && ($fieldCardinality == -1 || $fieldCardinality > 1) && is_array($fieldVal)){
            foreach ($fieldVal as $value) {
              # code...
              $fieldWidget['#default_value'][] = $value;
            }
          }
          else if(!empty($fieldVal)){
            $fieldWidget['#default_value'] = $fieldVal;
          }
          break;

        case 'boolean':
          # code...
          $fieldWidget['value']['#default_value'] = $fieldVal;
          break;

        case 'entity_reference':
          # code...
          $target_bundle = Key($fieldWidget[0]['target_id']['#selection_settings']['target_bundles']);
          $target_type = $fieldWidget[0]['target_id']['#target_type'];
        

          if($target_type == "taxonomy_term"){
            if(is_array($fieldVal) && !empty($fieldVal)){
              foreach ($fieldVal as $termName) {
                # code...
                $termArray = [
                  'name' => $termName,
                  'vid' => $target_bundle
                ];

                $taxos_obj = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties($termArray);
                $refObject[] = key($taxos_obj);
              }
            }
            else if(!empty($fieldVal)){
              # code...
              $termArray = [
                'name' => $fieldVal,
                'vid' => $target_bundle
              ];

              $taxos_obj = \Drupal::entityManager()->getStorage('taxonomy_term')->loadByProperties($termArray);
              $refObject = key($taxos_obj);
            } 
            if(empty($refObject)){
              $fields[] = $fieldKey;
            }           
          }
          else if($target_type == "user"){
            if(is_array($fieldVal) && !empty($fieldVal)){
              foreach ($fieldVal as $userEmail) {
                if(filter_var($userEmail, FILTER_VALIDATE_EMAIL)){
                   # code...
                  $user = user_load_by_mail($userEmail);
                  if(!empty($user)){
                    $userObject[] = $user->id();
                  }
                }
                else{
                  break;
                }               
              }
              $fields[] = $fieldKey;
            }
            else if(!empty($fieldVal)){
              if(filter_var($fieldVal, FILTER_VALIDATE_EMAIL)){
                # code...
                $user = user_load_by_mail($fieldVal);
                if(!empty($user)){
                  $userObject = $user->id();
                }
              }
              else{
                $fields[] = $fieldKey;
              }
            }

            if(empty($userObject)){
              $fields[] = $fieldKey;
            }
          }
      
          if(!empty($refObject) && ($fieldCardinality == -1 || $fieldCardinality > 1) && $target_type == "taxonomy_term"){
            foreach ($refObject as $refVal) {
              # code...
              $fieldWidget[$key] = $fieldWidget[0];
              $fieldWidget[$key++]['target_id']['#default_value'] = \Drupal\taxonomy\Entity\Term::load($refVal);
            }
          }else if(!empty($refObject)){
            $fieldWidget[0]['target_id']['#default_value'] = \Drupal\taxonomy\Entity\Term::load($refObject);
          }

          if(!empty($userObject) && ($fieldCardinality == -1 || $fieldCardinality > 1) && $target_type == "user"){
            foreach ($userObject as $userVal) {
              # code...
              $fieldWidget[$key] = $fieldWidget[0];
              $fieldWidget[$key++]['target_id']['#default_value'] = \Drupal\user\Entity\User::load($userVal);
            }
          }else if(!empty($userObject)){
            $fieldWidget[0]['target_id']['#default_value'] = \Drupal\user\Entity\User::load($userObject);
          }
          break;

        case 'datetime':
          # code...
          $dateFormat = $fieldWidget[0]['value']['#date_date_format'];
          $timeFormat = $fieldWidget[0]['value']['#date_time_format'];
          
          if(!empty($dateFormat) && !empty($timeFormat)){
            $date = date_create($fieldVal);
            $dateTime = \Drupal\Core\Datetime\DrupalDateTime::createFromDateTime($date);
            $fieldWidget[0]['value']['#default_value'] = $dateTime;
          }
          else if(!empty($dateFormat) && empty($timeFormat)){
            $date = date_create($fieldVal);
            $dateTime = \Drupal\Core\Datetime\DrupalDateTime::createFromDateTime($date);
            $fieldWidget[0]['value']['#default_value'] = $dateTime;
          }
          break;

        case 'string':
          if(!empty($fieldVal) && ($fieldCardinality == -1 || $fieldCardinality > 1) && is_array($fieldVal)){
            foreach ($fieldVal as $value) {
              # code...
              $fieldWidget[$key] = $fieldWidget[0];
              $fieldWidget[$key++]['value']['#default_value'][] = $value;
            }
          }
          else if(!empty($fieldVal)){
            $fieldWidget[0]['value']['#default_value'] = $fieldVal;
          }
          break;

        case 'file':
        case 'image':
          # code...
          if(!empty($fieldVal) && ($fieldCardinality == -1 || $fieldCardinality > 1) && is_array($fieldVal)){
              foreach ($fieldVal as $file) {
                # code...
                if(filter_var($file, FILTER_VALIDATE_URL)){
                  $fid = \Drupal\simple_node_importer\Services\GetServices::getImageFID($file);
                  if(!empty($fid)){
                    $fieldWidget[$key] = $fieldWidget[0];
                    $fieldWidget[$key++]['#default_value']['fids'][] = $fid;
                  }      
                }
                else{
                   $fields[] = $fieldKey;   
                }      
              }
                             
          }
          else if(!empty($fieldVal)){
            if(filter_var($fieldVal, FILTER_VALIDATE_URL)){
              $fid = \Drupal\simple_node_importer\Services\GetServices::getImageFID($fieldVal);
              if(!empty($fid)){
                $fieldWidget[0]['#default_value']['fids'] = array($fid);
              }      
            }
            else{
                $fields[] = $fieldKey;
            }      
          }      
          break;

        case 'email':
          if(!empty($fieldVal) && ($fieldCardinality == -1 || $fieldCardinality > 1) && is_array($fieldVal)){
            foreach($fieldVal as $email){
              if(filter_var($email, FILTER_VALIDATE_EMAIL)){
                #code..
                $fieldWidget[$key] = $fieldWidget[0];
                $fieldWidget[$key++]['value']['#default_value'] = $email;
              }
              else{
                $fields[] = $fieldKey;
              }
            }
            
          }
          else if(!empty($fieldVal)){
            if(filter_var($fieldVal, FILTER_VALIDATE_EMAIL)){
              #code..
              $fieldWidget[0]['value']['#default_value'] = $fieldVal;
            }
            else{
              $fields[] = $fieldKey;
            }
          }         
          break;

        case 'link':
          if(!empty($fieldVal) && ($fieldCardinality == -1 || $fieldCardinality > 1) && is_array($fieldVal)){
            foreach($fieldVal as $link){
              if(filter_var($link, FILTER_VALIDATE_URL)){
                #code..
                $fieldWidget[$key] = $fieldWidget[0];
                $fieldWidget[$key++]['uri']['#default_value'][] = $link;
              }
              else{
                $fields[] = $fieldKey;
              }         
            }
            
          }
          else if(!empty($fieldVal)){
            if(filter_var($fieldVal, FILTER_VALIDATE_URL)){
              #code..
              $fieldWidget[0]['uri']['#default_value'] = $fieldVal;
            }
            else{
              $fields[] = $fieldKey;
            }      
          }
          break;
      }
    }
    else{

      if($fieldKey == 'title'){
        $fieldWidget[0]['value']['#default_value'] = $fieldVal;
      }

      // for user bundle type
      if(in_array($fieldKey, ['name', 'mail', 'roles'])){
        $fieldWidget['#default_value'] = $fieldVal;
      }
      
      if($fieldKey == 'uid' && !empty($fieldVal)){
        if(filter_var($fieldVal, FILTER_VALIDATE_EMAIL)){
          $user = user_load_by_mail($fieldVal);
          if(!empty($user)){
            $fieldWidget[0]['target_id']['#default_value'] = \Drupal\user\Entity\User::load($user->id());
          }
        }
        else{
          $fields[] = $fieldKey;
        }
      }
    }
    
    if (!empty($fields)){
      $result['fieldWidget'] = $fieldWidget;
      $result['bugField'] = $fields;
      return $result;
    }
    else{
      return $fieldWidget;
    }
        
  }


  public static function getFieldInfo($entity_type, $fieldKey, $bundle){
    
    $field_info = FieldStorageConfig::loadByName($entity_type, $fieldKey);

    $entityManager = \Drupal::service('entity_field.manager');
    $field_definition = $entityManager->getFieldDefinitions($entity_type, $bundle);
    $fieldStorageDefinition = $entityManager->getFieldStorageDefinitions($entity_type, $bundle);
    
    $fieldProperties = $field_definition[$fieldKey];
  

    $fieldLabel = $field_info->getLabel();
    $fieldType = $field_info->getType();
    
    $fieldTypeProvider = $field_info->getTypeProvider();
    
    $fieldCardinality = $field_info->getCardinality();
    $fieldIsRequired = $fieldProperties->isRequired();

    $fieldInfoArray = [
      'fieldLabel' => $fieldLabel,
      'fieldType' => $fieldType,
      'fieldTypeProvider' => $fieldTypeProvider,
      'fieldCardinality' => $fieldCardinality,
      'fieldIsRequired' => $fieldIsRequired,
    ];

    return $fieldInfoArray;
  }

}
