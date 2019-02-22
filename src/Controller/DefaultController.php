<?php /**
 * @file
 * Contains \Drupal\simple_node_importer\Controller\DefaultController.
 */

namespace Drupal\simple_node_importer\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the simple_node_importer module.
 */
class DefaultController extends ControllerBase {

  public function simple_node_importer_create_mapping_fields($type, \Drupal\node\NodeInterface $node) {
    // Unset the session on batch start operation.
    if (isset($_SESSION['file_upload_session']) && !empty($_SESSION['file_upload_session'])) {
      unset($_SESSION['file_upload_session']);
    }
    $operations = [];
    $map_values = $_SESSION['mapvalues'];
    $csv_uri = $node->field_upload_csv[0]->uri;
    $handle = fopen($csv_uri, 'r');
    $columns = [];
    $columns = array_values(simple_node_importer_getallcolumnheaders($node->field_upload_csv[\Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED][0]));
    $record = [];
    $session_fields = array_keys($map_values);
    $i = 1;
    while ($row = fgetcsv($handle)) {
      if ($i == 1) {
        $i++;
        continue;
      }
      foreach ($row as $i => $field) {
        $column1 = str_replace(' ', '_', strtolower($columns[$i]));
        foreach ($session_fields as $field_name) {
          if ($map_values[$field_name] == $column1) {
            $record[$field_name] = $field;
          }
          else {
            if (is_array($map_values[$field_name])) {
              $multiple_fields = array_keys($map_values[$field_name]);
              foreach ($multiple_fields as $i => $m_fields) {
                if ($m_fields == $column1) {
                  $record[$field_name][$i] = $field;
                }
              }
            }
          }
        }
      }
      $record['nid'] = $node->id();
      $record['type'] = $type;
      $operations[] = ['simple_node_importer_make_nodes', [$record]];
    }
    $batch = [
      'title' => t('Creating Nodes Finally.'),
      'operations' => $operations,
      'finished' => 'simple_node_importer_sni_batch_finished',
      'init_message' => t('Node Creation Is Starting.'),
      'progress_message' => t('Processed @current out of @total.'),
      'error_message' => t('Node creation has encountered an error.'),
    ];
    // Set the batch operation.
    batch_set($batch);
    batch_process('node/' . $node->id());
    fclose($handle);
  }

  public function simple_node_importer_delete_node($option, \Drupal\node\NodeInterface $node) {
    print_r('redirecton successful');die;
    // Unset the session on cancel operation.
    if ($_SESSION['file_upload_session']) {
      unset($_SESSION['file_upload_session']);
    }
    if ($node) {
      $node->id()->delete();
    }
    drupal_goto('node/add/simple-node');
  }

  public function simple_node_importer_node_resolution_center() {
    // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $breadcrumb = array(
//     l(t('Home'), NULL),
//     t('Node importer'),
//     t('Resolution center'),
//   );

    drupal_set_breadcrumb($breadcrumb);
    $tableheader = [
      ['data' => t('Sr no')],
      ['data' => t('Content Type')],
      [
        'data' => t('Date of import')
        ],
      ['data' => t('Successful')],
      ['data' => t('Failures')],
      [
        'data' => t('Uploaded By')
        ],
      ['data' => t('Operations')],
    ];
    // A variable to hold the row information for each table row.
    $rows = [];
    $srno = 1;
    $query_record = db_select('node', 'n');
    $query_record->innerJoin('node_resolution', 'nr', 'nr.sni_nid=n.nid');
    $query_record->fields('n', ['nid', 'uid', 'type', 'created']);
    $query_record->fields('nr');
    $query_record->groupBy('nr.sni_nid');
    $result = $query_record->execute();
    foreach ($result as $data) {
      $row = [];
      $row[] = ['data' => $srno];
      $ctype_name = node_type_get_name($data->type);
      $row[] = ['data' => $ctype_name];
      // Convert timestamp to date & time.
      $formatted_date = date('d-M-Y', $data->created);
      $row[] = ['data' => $formatted_date];
      $status = unserialize($data->status);
      $row[] = ['data' => $status['success']];
      $row[] = ['data' => $status['fail']];
      $author = db_query('Select name from users where uid = :userid', [
        ':userid' => $data->uid
        ])->fetchField();
      $row[] = ['data' => $author];
      // @FIXME
      // l() expects a Url object, created from a route name or external URI.
      // $row[] = array(
      //       'data' => l(t('DownloadCSV'), 'nodeimporter/node/' . $data->nid . '/download-csv') . '&nbsp; | &nbsp;' . l(t('Delete'), 'node/' . $data->nid . '/delete', array('query' => array('destination' => 'admin/config/development/snodeimport/resolution-center'))),
      //     );

      $srno++;
      $rows[] = ['data' => $row];
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

  public function simple_node_importer_resolution_center_download_csv(\Drupal\node\NodeInterface $node) {
    $failed_rows = simple_node_importer_get_failed_rows($node->id());
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

}
