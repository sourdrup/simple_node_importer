<?php

/**
 * @file
 * Contains \Drupal\simple_node_importer\Form\SimpleUserImporterMappingForm.
 */

namespace Drupal\simple_node_importer\Form;

use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Render\Element;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;


class SimpleUserImporterMappingForm extends FormBase {
   protected $tempStore;
   protected $services;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   */
  public function __construct($GetServices,PrivateTempStoreFactory $temp_store_factory) {
    $this->services = $GetServices;
    $this->tempStore = $temp_store_factory->get('simple_node_importer');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_node_importer_mapping_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state,$option = NULL, \Drupal\node\NodeInterface $node = NULL) {
    global $base_url;
    $type = 'module';
    $module = 'simple_node_importer';
    $filepath = $base_url . '/' . drupal_get_path($type, $module) . '/css/files/mapping.png';
    $fid = $node->get('field_upload_csv')->getValue()[0]['target_id'];
    $file = \Drupal\file\Entity\File::load($fid);
    $uri = $file->getFileUri();
    $url = \Drupal\Core\Url::fromUri(file_create_url($uri))->toString();
    if (empty($node)) {
      $type = 'Simple Node Importer';
      $message = 'Node object is not valid.';
      \Drupal::logger($type)->error($message, []);
    }
    elseif ($this->tempStore->get('file_upload_session') == FALSE) {
      $response = new RedirectResponse('/node/add/simple-node');
      $response->send();
    }
    else {
      // Options to be listed in File Column List.
      $headers = $this->services->simple_node_importer_getallcolumnheaders($uri);
      $entity_type = $option;
      $selected_option = $option;
      $form_mode = 'default';
      $type = 'mapping';
      $get_field_list = $this->services->snp_get_field_list($entity_type,$selected_option, $type);
      $parameters = array('option' => $option,'node' =>$node->id());
      $this->tempStore->set('parameters', $parameters);
      // Add HelpText to the mapping form.
      $form['helptext'] = [
        '#theme' => 'mapping_help_text_info',
        '#fields' => array(
          // 'allowed_date_format' => $allowed_date_format,
          'filepath' => $filepath,
        )
    
      ];
      // Add theme table to the mapping form.
      $form['mapping_form']['#theme'] = 'simple_node_import_table';
      // Mapping form.
      $defaultFieldArr = ['name', 'mail','status','roles'];
      foreach ($get_field_list as $key => $field) {
          if ($entity_type == 'user'){
            $field_name = $field->getName();
            if( in_array($key,$defaultFieldArr)){
                  $field_label = $field->getLabel()->render();
                  $fieldcardinality = $field->getCardinality();
               }
            else{
                $field_info = \Drupal\field\Entity\FieldStorageConfig::loadByName($entity_type, $field_name);
                 $fieldcardinality =$field_info->get('cardinality');  
                $field_label = $field->getLabel();
             }
       }
        if ($fieldcardinality == -1 || $fieldcardinality > 1) {
            $form['mapping_form'][$key] = [
              '#type' => 'select',
              '#title' => $field_label,
              '#options' => $headers,
              '#multiple' => TRUE,
              '#required' => ($field->isRequired()) ? TRUE : FALSE,
              '#empty_option' => t('Select'),
              '#empty_value' => '',
            ];
          }
          else {
            $form['mapping_form'][$key] = [
              '#type' => 'select',
              '#title' => $field_label,
              '#options' => $headers,
              '#required' => ($field->isRequired()) ? TRUE : FALSE,
              '#empty_option' => t('Select'),
              '#empty_value' => '',
            ];
          }
      }

      // Get the preselected values for form fields.
      $form = $this->services->simple_node_importer_getpreselectedvalues($form, $headers);
          
      $form['import'] = [
        '#type' => 'submit',
        '#value' => t('Import'),
        '#weight' => 49,
      ];
     // $this->tempStore->set('parameters', $parameters);
      $form['cancel'] = [
        '#type' => 'submit',
        '#value' => t('cancel'),
        '#weight' => 3,
        '#submit' => array('::snp_redirect_to_cancel')
      ];
      return $form;
    }
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $valarray = [];
    $duplicates = [];
    $count_array = [];
    $form_state->cleanValues();
    foreach ($form_state->getValues() as $key => $val) {
      if ($val && is_array($val)) {
        foreach ($val as $v) {
          $valarray[] = $v;
        }
      }
      elseif ($val) {
        $valarray[] = $val;
      }
    }
    $count_array = array_count_values($valarray);
    foreach ($count_array as $field => $count) {
      if ($count > 1) {
        $duplicates[] = $field;
      }
    }
    foreach ($duplicates as $duplicate_val) {
      foreach ($form_state->getValues() as $key => $val) {
        if ($val == $duplicate_val) {
          $form_state->setErrorByName($key, t('Duplicate Mapping detected for %duplval', [
            '%duplval' => $duplicate_val
            ]));
        }
        elseif (is_array($val)) {
          foreach ($val as $v) {
            if ($v == $duplicate_val) {
              $form_state->setErrorByName($key, t('Duplicate Mapping detected for %duplval', [
                '%duplval' => $duplicate_val
                ]));
            }
          }
        }
      }
    }
    // Remove Duplicate Error Messages.
    if (isset($_SESSION['messages']['error'])) {
      $_SESSION['messages']['error'] = array_unique($_SESSION['messages']['error']);
    }
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $haystack = 'snp_';
    foreach ($form_state->getValues() as $key => $val) {
      if (strpos($key, $haystack) === FALSE){
        $mapvalues[$key] = $val;        
      }
    }
    $this->tempStore->set('mapvalues', $mapvalues);
    // Remove unnecessary values.
    $parameters = $this->tempStore->get('parameters');
    $form_state->cleanValues();
    $form_state->setRedirect('simple_node_importer.user_confirmation_form', $parameters);
  }

  /**
   * {@inheritdoc}
   */
   public static function create(ContainerInterface $container) {
     return new static(
       $container->get('snp.get_services'),
       $container->get('user.private_tempstore')
     );
   }

   public function snp_redirect_to_cancel(array &$form, FormStateInterface $form_state)
   {
     $parameters = $this->tempStore->get('parameters');     
     $form_state->setRedirect('simple_node_importer.delete_node', $parameters);
   }
}
?>
