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
function simple_node_importer_get_content_types() {
  $content_types = array();
  foreach (node_type_get_types() as $key => $value) {
    $content_types[$key] = $value->name;
  }
  return $content_types;
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
function simple_node_importer_get_field_list($content_type = '') {

  if (!empty($content_type)) {
    $field_instance = field_info_instances("node", $content_type);
    $extra_fields = field_info_extra_fields('node', $content_type, 'form');
    if (array_key_exists('title', $extra_fields)) {
      $extra_fields['title']['required'] = TRUE;
      $field_instance = array('title' => $extra_fields['title']) + $field_instance;
    }
    return $field_instance;
  }
  else {
    return "";
  }
}

/**
 * Helper function to generate CSV file.
 */
function simple_node_importer_dynode_select_create_csv($content_type) {
  $filename = $content_type . '_template.csv';

  header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
  header('Content-Description: File Transfer');
  header("Content-type: text/csv");
  header("Content-Disposition: attachment; filename={$filename}");
  header("Expires: 0");
  header("Pragma: public");
  $csv = array();

  foreach (simple_node_importer_get_field_list($content_type) as $value) {
    $csv[] = $value['label'];
  }

  $fh = @fopen('php://output', 'w');

  // Put the data into the stream.
  fputcsv($fh, $csv);
  fclose($fh);
  // Make sure nothing else is sent, our file is done.
  exit;
}

/**
 * Helper functo to get header array().
 */
function simple_node_importer_getallcolumnheaders($file) {
  $uploaded_fileuri = $file['uri'];
  $handle = fopen($uploaded_fileuri, 'r');
  $row = fgetcsv($handle);
  foreach ($row as $value) {
    // code...
    $key = strtolower(str_replace(" ", "_", $value));
    $column[$key] = $value;
  }
  return $column;
}

/**
 * Return Field Array().
 */
function simple_node_importer_getfieldarray($contenttype) {
  $fields_array = simple_node_importer_get_field_list($contenttype);
  foreach ($fields_array as $field) {
    if (isset($field['field_name']) || array_key_exists('field_name', $field)) {
      $field_list[$field['field_name']] = $field['label'];
    }
  }
  return $field_list;
}

/**
 * Helper Function to auto select the mapping field values.
 */
function simple_node_importer_getpreselectedvalues($form, $headers) {
  foreach ($form['mapping_form'] as $field => $attributes) {
    foreach ($headers as $field_name => $label) {
      if (is_array($attributes)) {
        if (strtolower($attributes['#title']) == strtolower($label)) {
          $form['mapping_form'][$field]['#default_value'] = $field_name;
        }
      }
    }
  }
  return $form;
}
