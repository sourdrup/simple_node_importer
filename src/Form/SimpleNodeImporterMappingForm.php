<?php

/**
 * @file
 * Contains \Drupal\simple_node_importer\Form\SimpleNodeImporterMappingForm.
 */

namespace Drupal\simple_node_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;


class SimpleNodeImporterMappingForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_node_importer_mapping_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state,$option = NULL,$node = NULL) {
    global $base_url;
    $type = 'module';
    $module = 'simple_node_importer';
    $filepath = $base_url . '/' . drupal_get_path($type, $module) . '/css/files/mapping.png';
    $node = \Drupal::entityManager()->getStorage('node')->load($node);
    $fid = $node->get('field_upload_csv')->getValue()[0]['target_id'];
    $file = \Drupal\file\Entity\File::load($fid);
    $uri = $file->getFileUri();
    $url = \Drupal\Core\Url::fromUri(file_create_url($uri))->toString();
    
    if (empty($node)) {
      $type = 'Simple Node Importer';
      $message = 'Node object is not valid.';
      \Drupal::logger($type)->error($message, []);
    }
    elseif (!$_SESSION['file_upload_session']) {
      drupal_goto('node/add/simple-node');
    }
    else {
      // Options to be listed in File Column List.
      $headers = \Drupal::service('snp.get_services')->simple_node_importer_getallcolumnheaders($url);
      
      // $headers = simple_node_importer_getallcolumnheaders($node['build_info']['args'][1]->field_upload_csv[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]);
      // $selected_content_type = $node['build_info']['args'][1]->field_content_type[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'];
      $selected_content_type = $node->get('field_select_content_type')->getValue()[0]['value'];
      // $get_field_list = snp_get_field_list($selected_content_type);
      $entity_type = 'node';
      $get_field_list =\Drupal::service('snp.get_services')->snp_get_field_list($entity_type,$selected_content_type);
      $allowed_date_format = NULL;
      foreach ($get_field_list as $field) {
        if (isset($field['widget']) && $field['widget']['type'] == 'date_text') {
          $allowed_date_format = $field['widget']['settings']['input_format'];
        }
      }

      // @FIXME
      // theme() has been renamed to _theme() and should NEVER be called directly.
      // Calling _theme() directly can alter the expected output and potentially
      // introduce security issues (see https://www.drupal.org/node/2195739). You
      // should use renderable arrays instead.
      // 
      // 
      // @see https://www.drupal.org/node/2195739
      // $outputtext = theme('mapping_help_text_info', array('allowed_date_format' => $allowed_date_format, 'filepath' => $filepath));
      // Add HelpText to the mapping form.
      $form['helptext'] = [
        '#type' => 'item',
        '#markup' => $outputtext,
      ];
      // Add theme table to the mapping form.
      $form['mapping_form']['#theme'] = 'simple_node_import_table';
      // Mapping form.
      $form['mapping_form']['title'] = [
        '#type' => 'select',
        '#title' => t('Title'),
        '#options' => $headers,
        '#empty_option' => t('Select'),
        '#empty_value' => '',
      ];
      foreach ($get_field_list as $key => $field) {
        // code...
        
        $field_name = key($field);
        $field_info = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', $field_name);
        if ($field_info['cardinality'] == -1 || $field_info['cardinality'] > 1) {
          $form['mapping_form'][$key] = [
            '#type' => 'select',
            '#title' => $field['label'],
            '#options' => $headers,
            '#multiple' => TRUE,
            '#required' => ($field['required'] == 1) ? TRUE : FALSE,
            '#empty_option' => t('Select'),
            '#empty_value' => '',
          ];
        }
        else {
          $form['mapping_form'][$key] = [
            '#type' => 'select',
            '#title' => $field['label'],
            '#options' => $headers,
            '#required' => ($field['required'] == 1) ? TRUE : FALSE,
            '#empty_option' => t('Select'),
            '#empty_value' => '',
          ];
        }
      }
      // Get the preselected values for form fields.
      $form = \Drupal::service('snp.get_services')->simple_node_importer_getpreselectedvalues($form, $headers);
      $form['import'] = [
        '#type' => 'submit',
        '#value' => t('Import'),
        '#weight' => 49,
        '#submit' => [
          'simple_node_importer_mapping_form_submit'
          ],
      ];
      // @FIXME
      // l() expects a Url object, created from a route name or external URI.
      // $form['cancel'] = array(
      //       '#markup' => l(t('Cancel'), 'simplenode/' . arg(1) . '/' . arg(2) . '/delete', array('attributes' => array('class' => array('cancel-button')))),
      //       '#weight' => 50,
      //     );

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
    $selected_content = arg(1);
    // Remove unnecessary values.
    $form_state->cleanValues();
    foreach ($form_state->getValues() as $key => $val) {
      $_SESSION['mapvalues'][$key] = $val;
    }
    $form_state->set(['redirect'], 'nodeimport/' . $selected_content . '/' . arg(2) . '/importing');
  }

}
?>
