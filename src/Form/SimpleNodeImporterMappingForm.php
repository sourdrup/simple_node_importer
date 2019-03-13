<?php

/**
 * @file
 * Contains \Drupal\simple_node_importer\Form\SimpleNodeImporterMappingForm.
 */

namespace Drupal\simple_node_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing;
use Drupal\Core\Session;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class SimpleNodeImporterMappingForm extends FormBase {
  
   protected $services;
   protected $sessionVariable;
   protected $sessionManager;
   protected $currentUser;
   protected $entityTypeManager;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   */
  public function __construct($GetServices, \Drupal\Core\TempStore\PrivateTempStoreFactory  $SessionVariable, \Drupal\Core\Session\SessionManagerInterface $session_manager, \Drupal\Core\Session\AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->services = $GetServices;
    $this->sessionVariable = $SessionVariable->get('simple_node_importer');
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_node_importer_mapping_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state,$option = NULL, NodeInterface $node = NULL) {
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
    elseif ($this->sessionVariable->get('file_upload_session') == FALSE) {
      $response = new RedirectResponse('/node/add/simple-node');
      $response->send();
    }
    else {
      // Options to be listed in File Column List.
      $headers = $this->services->simple_node_importer_getallcolumnheaders($uri);
      $selected_content_type = $node->get('field_select_content_type')->getValue()[0]['value'];
      
      $entity_type = $node->getEntityTypeId();

      $type = 'mapping';
      
      $get_field_list = $this->services->snp_get_field_list($entity_type,$selected_content_type, $type);

      $allowed_date_format = NULL;
      //dsm($get_field_list);
      /*foreach ($get_field_list as $field) {
        if (isset($field['widget']) && $field['widget']['type'] == 'date_text') {
          $allowed_date_format = $field['widget']['settings']['input_format'];
        }
      }*/

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
      foreach ($get_field_list as $key => $field) {
        // code...
        if (method_exists ($field->getLabel() , 'render')) {
          $form['mapping_form'][$key] = [
            '#type' => 'select',
            '#title' => $field->getLabel()->render(),
            '#options' => $headers,
            '#empty_option' => t('Select'),
            '#empty_value' => '',
          ];
        }
        else {
          //print_r($field); exit;
          $field_name = $field->getName();
          $field_label = $field->getLabel();
          $field_info = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', $field_name);

          if (!empty($field_info) && ($field_info->get('cardinality') == -1 || $field_info->get('cardinality') > 1)) {
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
      }

      // Get the preselected values for form fields.
      $form = $this->services->simple_node_importer_getpreselectedvalues($form, $headers);

      $form['snp_nid'] = [
        '#type' => 'hidden',
        '#value' => $node->id()
      ];

      $form['import'] = [
        '#type' => 'submit',
        '#value' => t('Import'),
        '#weight' => 49,
      ];

      $parameters = array('option' => $option,'node' =>$node->id());
      $this->sessionVariable->set('parameters', $parameters);
      $form['cancel'] = [
        '#type' => 'submit',
        '#value' => t('cancel'),
        '#weight' => 3,
        '#submit' => array('::snp_redirect_to_cancel')
      ];

      return $form;
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
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

  public function submitForm(array &$form, FormStateInterface $form_state) {
   
    // Remove unnecessary values.
    $form_state->cleanValues();

    $haystack = 'snp_';

    foreach ($form_state->getValues() as $key => $val) {
      if (strpos($key, $haystack) === FALSE){
        $mapvalues[$key] = $val;        
      }
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $file_storage = $this->entityTypeManager->getStorage('file');

    $snp_nid = $form_state->getValue('snp_nid');

    $node = $node_storage->load($snp_nid);

    $bundleType = $node->get('field_select_content_type')->getValue()[0]['value'];
    
    $this->sessionVariable->set('mapvalues', $mapvalues);

    $parameters = array('type' => $bundleType,'node' => $snp_nid);
    
    $form_state->setRedirect('simple_node_importer.confirm_importing', $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function snp_redirect_to_cancel(array &$form, FormStateInterface $form_state)
   {
      $parameters = $this->sessionVariable->get('parameters');
      $form_state->setRedirect('simple_node_importer.delete_node', $parameters);
   }

  public static function create(ContainerInterface $container) {
   return new static(
     $container->get('snp.get_services'),
     $container->get('user.private_tempstore'),
     $container->get('session_manager'),
     $container->get('current_user'),
     $container->get('entity_type.manager')
   );
  }
}
?>
