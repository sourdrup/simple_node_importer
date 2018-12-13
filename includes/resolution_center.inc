<?php

/**
 * @file
 * File will contain helper functions for resolution center.
 */

/**
 * Helper function for node resolution center.
 */
function simple_node_importer_node_resolution_center() {
  // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $breadcrumb = array(
//     l(t('Home'), NULL),
//     t('Node importer'),
//     t('Resolution center'),
//   );

  drupal_set_breadcrumb($breadcrumb);
  $tableheader = array(
    array('data' => t('Sr no')),
    array('data' => t('Content Type')),
    array('data' => t('Date of import')),
    array('data' => t('Successful')),
    array('data' => t('Failures')),
    array('data' => t('Uploaded By')),
    array('data' => t('Operations')),
  );
  // A variable to hold the row information for each table row.
  $rows = array();
  $srno = 1;
  $query_record = db_select('node', 'n');
  $query_record->innerJoin('node_resolution', 'nr', 'nr.sni_nid=n.nid');
  $query_record->fields('n', array('nid', 'uid', 'type', 'created'));
  $query_record->fields('nr');
  $query_record->groupBy('nr.sni_nid');
  $result = $query_record->execute();
  foreach ($result as $data) {
    $row = array();
    $row[] = array('data' => $srno);
    $ctype_name = node_type_get_name($data->type);
    $row[] = array('data' => $ctype_name);
    // Convert timestamp to date & time.
    $formatted_date = date('d-M-Y', $data->created);
    $row[] = array('data' => $formatted_date);
    $status = unserialize($data->status);
    $row[] = array('data' => $status['success']);
    $row[] = array('data' => $status['fail']);
    $author = db_query('Select name from users where uid = :userid', array(':userid' => $data->uid))->fetchField();
    $row[] = array('data' => $author);
    // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $row[] = array(
//       'data' => l(t('DownloadCSV'), 'nodeimporter/node/' . $data->nid . '/download-csv') . '&nbsp; | &nbsp;' . l(t('Delete'), 'node/' . $data->nid . '/delete', array('query' => array('destination' => 'admin/config/development/snodeimport/resolution-center'))),
//     );

    $srno++;
    $rows[] = array('data' => $row);
  }
  // Use theme_table to produce our table.
  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
// 
// 
// @see https://www.drupal.org/node/2195739
// $output = theme('table', array('header' => $tableheader, 'rows' => $rows));

  return $output;
}

/**
 * Callback : Called when batch process is finished.
 */
function simple_node_importer_sni_batch_finished($success, $results, $operations) {
  if ($success) {
    // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $rclink = l(t('Resolution Center'), 'nodeimporter/resolution-center');

    $created_count = !empty($results['created']) ? $results['created'] : NULL;
    $failed_count = !empty($results['failed']) ? $results['failed'] : NULL;

    if ($created_count && !$failed_count) {
      $import_status = t("Nodes successfully created: %created_count", array('%created_count' => $created_count));
    }
    elseif (!$created_count && $failed_count) {
      $import_status = t('Nodes import failed: %failed_count .To view failed records, please visit', array('%failed_count' => $failed_count)) . $rclink;
    }
    else {
      $import_status = t('Nodes successfully created: %created_count . Nodes import failed: %failed_count .To view failed records, please visit', array('%created_count' => $created_count, '%failed_count' => $failed_count)) . $rclink;
    }
    if (isset($results['failed']) && !empty($results['failed'])) {
      // Add Failed nodes to Resolution Table.
      simple_node_importer_add_node_resolution_center($results);
    }
    drupal_set_message("Node import completed! Import status: $import_status");
  }
  else {
    $error_operation = reset($operations);
    $message = t('An error occurred while processing %error_operation with arguments: @arguments', array(
      '%error_operation' => $error_operation[0],
      '@arguments' => print_r($error_operation[1], TRUE),
    ));
    drupal_set_message($message, 'error');
  }
}

/**
 * Creates node for specified type of mapped data.
 */
function simple_node_importer_make_nodes($record, &$context) {
  $user = \Drupal::currentUser();
  $node = new StdClass();
  $node->type = $record['type'];
  $node->title = ($record['title']) ? $record['title'] : $node->result[] = $record;
  $node->uid = $user->uid;
  $node->language = 'en';
  $node->body[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'] = ($record['body']) ? $record['body'] : '';
  $field_names = array_keys($record);
  $batch_result = simple_node_importer_check_field_widget($field_names, $record, $node);
  if (!empty($batch_result->result) || empty($batch_result->title)) {
    if (!isset($context['results']['failed'])) {
      $context['results']['failed'] = 0;
    }
    $context['results']['failed']++;
    $batch_result->result['sni_id'] = $context['results']['failed'];
    $context['results']['sni_nid'] = $record['nid'];
    $context['results']['data'][] = serialize($batch_result->result);
  }
  else {
    $batch_result->save();
    if ($batch_result->nid) {
      if (!isset($context['results']['created'])) {
        $context['results']['created'] = 0;
      }
      $context['results']['created']++;
    }
    else {
      $context['results']['failed']++;
      $batch_result->result['sni_id'] = $context['results']['failed'];
      $context['results']['sni_nid'] = $record['nid'];
      $context['results']['data'] = $batch_result->result;
    }
  }
}

/**
 * Checks the widget type of each field.
 */
function simple_node_importer_check_field_widget($field_names, $data, $node) {
  unset($field_names['type']);
  foreach ($field_names as $field_machine_name) {
    $widget_type = field_info_field($field_machine_name);
    $field_info = field_info_instance('node', $field_machine_name, $node->type);
    switch ($widget_type['type']) {
      case 'email':
        if ($field_info['required'] == 1 && $data[$field_machine_name] == '') {
          $node->result[] = $data;
        }
        elseif ($data[$field_machine_name] != '' && !valid_email_address($data[$field_machine_name])) {
          $node->result[] = $data;
        }
        else {
          $email = $data[$field_machine_name];
          $i = 0;
          if (is_array($email)) {
            foreach ($email as $eids) {
              // code...
              $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][$i]['email'] = trim($eids);
            }
          }
          else {
            $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][$i]['email'] = trim($data[$field_machine_name]);
          }
        }
        break;

      case 'image':
        if ($field_info['required'] == 1 && $data[$field_machine_name] == '') {
          $node->result[] = $data;
        }
        else {
          $image_url = $data[$field_machine_name];
          $i = 0;
          if (is_array($image_url)) {
            foreach ($image_url as $imgurl) {
              // code...
              $remote_doc_path = $imgurl;
              $file = system_retrieve_file($remote_doc_path, NULL, TRUE, FILE_EXISTS_REPLACE);
              $file->status = 1;
              $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][$i]['fid'] = $file->fid;
              $i++;
            }
          }
          else {
            $remote_doc_path = $image_url;
            $file = system_retrieve_file($remote_doc_path, NULL, TRUE, FILE_EXISTS_REPLACE);
            $file->status = 1;
            $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][$i]['fid'] = $file->fid;
          }

        }
        break;

      case 'taxonomy_term_reference':
        $vocabulary_name = $widget_type['settings']['allowed_values'][0]['vocabulary'];
        $voc_obj = simple_node_importer_get_vocabulary_by_name($vocabulary_name);
        if ($field_info['required'] == 1 && $data[$field_machine_name] == '') {
          $node->result[] = $data;
        }
        else {
          if (is_array($data[$field_machine_name])) {
            foreach ($data[$field_machine_name] as $k => $term_name) {
              $taxos_obj = simple_node_importer_get_taxonomy_by_name($term_name, $voc_obj->name);
              // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/simple_node_importer.settings.yml and config/schema/simple_node_importer.schema.yml.
$allw_term = \Drupal::config('simple_node_importer.settings')->get('simple_node_importer_allow_add_term');
              if (!$taxos_obj && $allw_term) {
                $term = (object) array(
                  'vid' => $voc_obj->vid,
                  'name' => $term_name,
                  'description' => t('Term added through SNI import module'),
                );
                $term->save();
                $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][$k]['tid'] = $term->tid;
              }
              else {
                $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][$k]['tid'] = !empty($taxos_obj->tid) ? $taxos_obj->tid : "";
              }
            }
          }
          else {
            $taxos_obj = simple_node_importer_get_taxonomy_by_name($data[$field_machine_name], $voc_obj->name);
            // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/simple_node_importer.settings.yml and config/schema/simple_node_importer.schema.yml.
$allw_term = \Drupal::config('simple_node_importer.settings')->get('simple_node_importer_allow_add_term');
            if (!$taxos_obj && $allw_term) {
              $term = array(
                'vid' => $voc_obj->vid,
                'name' => $term_name,
                'description' => t('Term added through SNI import module'),
              );
              taxonomy_save_term($term);
              $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['tid'] = $term->tid;
            }
            else {
              $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['tid'] = !empty($taxos_obj->tid) ? $taxos_obj->tid : "";
            }
          }
        }
        break;

      case 'text':
      case 'text_long':
      case 'text_with_summary':
        if ($field_info['required'] == 1 && $data[$field_machine_name] == '') {
          $node->result[] = $data;
        }
        else {
          $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'] = trim($data[$field_machine_name]);
        }
        break;

      case 'list_boolean':
        if ($field_info['required'] == 1 && $data[$field_machine_name] == '') {
          $node->result[] = $data;
        }
        else {
          $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'] = ($data[$field_machine_name] == 1) ? $data[$field_machine_name] : ((strtolower($data[$field_machine_name]) == 'y') ? 1 : 0);
        }
        break;

      case 'datetime':
        if ($field_info['required'] == 1 && $data[$field_machine_name] == '') {
          $node->result[] = $data;
        }
        else {
          if (is_array($data[$field_machine_name])) {
            foreach ($data[$field_machine_name] as $k => $field_value) {
              $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][$k]['value'] = date_format(date_create($field_value), 'Y-m-d H:i:s');
            }
          }
          else {
            $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'] = date_format(date_create($data[$field_machine_name]), 'Y-m-d H:i:s');
          }
        }
        break;

      case 'number_integer':
      case 'number_float':
        if ($field_info['required'] == 1 && $data[$field_machine_name] == '') {
          $node->result[] = $data;
        }
        else {
          if (is_array($data[$field_machine_name])) {
            foreach ($data[$field_machine_name] as $k => $field_value) {
              $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][$k]['value'] = $field_value;
            }
          }
          else {
            $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'] = $data[$field_machine_name];
          }
        }
        break;

      case 'list_text':
      case 'list_float':
      case 'list_integer':
        if ($field_info['required'] == 1 && $data[$field_machine_name] == '') {
          $node->result[] = $data;
        }
        else {
          $allowed_values = list_allowed_values($widget_type);
          if (is_array($data[$field_machine_name])) {
            foreach ($data[$field_machine_name] as $k => $field_value) {
              $key_value = array_search(strtolower($field_value), array_map('strtolower', $allowed_values));
              $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][$k]['value'] = $key_value;
            }
          }
          else {
            $key_value = array_search(strtolower($data[$field_machine_name]), array_map('strtolower', $allowed_values));
            $node->{$field_machine_name}[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]['value'] = $key_value;
          }
        }
        break;
    }// end of switch case
  }// end of foreach
  return $node;
}

/**
 * Add data to node resolution table.
 */
function simple_node_importer_add_node_resolution_center($result) {
  if (isset($result['data']) && !empty($result['data'])) {

    $import_status = array(
      'success' => !empty($result['created']) ? $result['created'] : "",
      'fail' => !empty($result['failed']) ? $result['failed'] : "",
    );

    $sni_nid = !empty($result['sni_nid']) ? $result['sni_nid'] : NULL;
    foreach ($result['data'] as $data) {
      $resolution_log = db_insert('node_resolution')
        ->fields(array(
          'sni_nid' => $sni_nid,
          'data' => $data,
          'reference' => simple_node_importer_generate_reference(10),
          'status' => serialize($import_status),
          'created' => REQUEST_TIME,
        ))
        ->execute();
    }

    if ($resolution_log) {
      drupal_set_message(t('Failed node add to resolution to edit.'));
    }
  }
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
function simple_node_importer_generate_reference($length = 10) {
  srand((double) microtime(TRUE) * 1000000);
  $string = "";
  $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
  for ($i = 0; $i < $length; $i++) {
    $string .= substr($chars, rand(0, strlen($chars)), 1);
  }
  return $string;
}

/**
 * Return a vocabulary obj ,matches the given name.
 *
 * @param string $vocabulary_name
 *   This is the name of the section which is required.
 *
 * @return Object
 *   This is the vocabulary object with the name
 *   or null if no such vocabulary exists.
 */
function simple_node_importer_get_vocabulary_by_name($vocabulary_name) {
  $vocabs = taxonomy_vocabulary_get_names();
  foreach ($vocabs as $vocab_object) {
    if (strtolower($vocab_object->name) == strtolower($vocabulary_name)) {
      return $vocab_object;
    }
  }
  return NULL;
}

/**
 * Return a taxonomy obj, matches the given name.
 *
 * @param string $taxonomy_name
 *   This is the name of the section which is required.
 * @param string $vocabulary_name
 *   This is the name of the section which is required.
 *
 * @return Object
 *   This is the taxonomy object with the name
 *   or null if no such taxonomy exists.
 */
function simple_node_importer_get_taxonomy_by_name($taxonomy_name, $vocabulary_name) {
  $taxos = taxonomy_get_term_by_name($taxonomy_name, $vocabulary_name = NULL);
  foreach ($taxos as $taxos_object) {
    if (strtolower($taxos_object->name) == strtolower($taxonomy_name)) {
      return $taxos_object;
    }
  }
  return NULL;
}

/**
 * Function to create CSV file for failed imports.
 *
 * @param int $node
 *   Failed nodes from import node nid.
 */
function simple_node_importer_resolution_center_download_csv($node) {
  $failed_rows = simple_node_importer_get_failed_rows($node->nid);
  if ($failed_rows) {
    $i = 1;
    foreach ($failed_rows as $col_val) {
      foreach ($col_val as $keycol => $keyfieldval) {
        if (is_array($keyfieldval) && !empty($keyfieldval)) {
          $j = 0;
          foreach ($keyfieldval as $keyfield) {
            if ($j == 0) {
              $col_val[$keycol] = $keyfield;
            }
            elseif (!empty($keyfield)) {
              $col_val[$keycol . "_" . $j] = $keyfield;
            }
            $j++;
          }
        }
        else {
          $col_val[$keycol] = $keyfieldval;
        }
      }
      $rows[] = $col_val;
      $i++;
    }
  }

  $filename = 'Import-failed-nodes.csv';
  drupal_add_http_header('Content-Type', 'text/csv');
  drupal_add_http_header('Content-Disposition', 'attachment;filename=' . $filename);
  $fp = fopen('php://output', 'w');
  $header_update = FALSE;
  foreach ($rows as $val) {
    foreach ($val as $key => $keyval) {
      if (!$header_update) {
        $headcol[] = ucwords(str_replace("field ", "", str_replace("_", " ", $key)));
      }
      $row[] = $keyval;
    }

    if (!$header_update) {
      fputcsv($fp, $headcol);
      $header_update = TRUE;
    }

    fputcsv($fp, $row);
  }

  fclose($fp);
  drupal_exit();
}

/**
 * Function to fetch failed nodes from node_resolution table.
 *
 * @param int $nid
 *   Failed nodes from import node nid.
 */
function simple_node_importer_get_failed_rows($nid) {
  $data = array();
  // Query to fetch failed data.
  $query_record = db_select('node_resolution', 'nr');
  $query_record->fields('nr', array('data'));
  $query_record->condition('nr.sni_nid', $nid);
  $result = $query_record->execute();

  foreach ($result as $k => $value) {
    // code...
    $data[$k] = unserialize($value->data);
    unset($data[$k]['sni_id']);
  }

  foreach ($data as $val) {
    foreach ($val as $valv) {
      if (isset($valv['nid']) || isset($valv['type'])) {
        unset($valv['nid']);
        unset($valv['type']);
      }
      $records[] = $valv;
    }
  }
  if (!empty($records)) {
    return $records;
  }
  else {
    return FALSE;
  }
}
