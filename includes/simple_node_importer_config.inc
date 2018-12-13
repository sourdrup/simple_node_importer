<?php

/**
 * @file
 * File will contain all helper functions.
 */

/**
 * Function to find out all content types with type node.
 *
 * @return array
 *   It will return associative array of available content types.
 */
/*function simple_node_importer_config_form($form, &$form_state) {

  foreach (node_type_get_types() as $key => $value) {
    $content_types[$key] = $value->name;
  }

  unset($content_types['simple_node']);
  $content_type_selected = array();
  // @FIXME
// // @FIXME
// // This looks like another module's variable. You'll need to rewrite this call
// // to ensure that it uses the correct configuration object.
// $content_type_select = variable_get('content_type_select');


  if (!empty($content_type_select)) {
    foreach ($content_type_select as $key => $value) {
      if ($value) {
        $content_type_selected[$key] = $value;
      }
    }
  }
  $form['fieldset_content_type'] = array(
    '#type' => 'fieldset',
    '#title' => t('Content type settings'),
    '#weight' => 1,
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );

  $form['fieldset_content_type']['content_type_select'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Select content type'),
    '#default_value' => isset($content_type_selected) ? $content_type_selected : '' ,
    '#options' => $content_types,
    '#description' => t('Configuration for the content type to be selected.'),
    '#required' => FALSE,
  );

  $form['fieldset_taxonomy_term_type'] = array(
    '#type' => 'fieldset',
    '#title' => t('Taxonomy Term settings'),
    '#weight' => 1,
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );

  $form['fieldset_taxonomy_term_type']['simple_node_importer_allow_add_term'] = array(
    '#type' => 'checkbox',
    '#title' => t('Allow adding new taxonomy terms.'),
    '#default_value' => \Drupal::config('simple_node_importer.settings')->get('simple_node_importer_allow_add_term'),
    '#description' => t('Check to allow adding term for taxonomy reference fields, if term is not available.'),
  );

  $form['fieldset_remove_importer'] = array(
    '#type' => 'fieldset',
    '#title' => t('Node remove settings'),
    '#weight' => 2,
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );
  // The options to display in our checkboxes.
  $option = array(
    'option' => t('Delete import logs.'),

  );
  $form['fieldset_remove_importer']['node_delete'] = array(
    '#title' => '',
    '#type' => 'checkboxes',
    '#description' => t('Select the checkbox to delete all import logs permanently.'),
    '#options' => $option,
  );

  return system_settings_form($form);
}*/

/**
 * Submit handler for simple_node_importer_config_form().
 */
/*function simple_node_importer_config_form_submit($form, &$form_state) {
  $node_setting = $form_state['values']['node_delete']['option'];
  $query = db_select('node', 'n');
  $query->fields('n', array('nid'));
  $query->condition('n.status', '1', '=');
  $query->condition('n.type', 'simple_node');
  $query->orderBy('created', 'DESC');
  $node_delete = $query->execute()->fetchCol();
  if ($node_setting === 'option') {
    node_delete_multiple($node_delete);
    drupal_set_message(t('%count nodes has been deleted.', array('%count' => count($node_delete))));
  }
}
*/