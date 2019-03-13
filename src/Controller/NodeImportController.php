<?php /**
 * @file
 * Contains \Drupal\simple_node_importer\Controller\DefaultController.
 */

namespace Drupal\simple_node_importer\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Routing;
use Drupal\Core\Session;
use Drupal\Core\Entity;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\file\Entity\File;

/**
 * Default controller for the simple_node_importer module.
 */
class NodeImportController extends ControllerBase {

  protected $services;
  protected $sessionVariable;
  protected $sessionManager;
  protected $currentUser;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   */
  public function __construct($GetServices, \Drupal\Core\TempStore\PrivateTempStoreFactory  $SessionVariable, \Drupal\Core\Session\SessionManagerInterface $session_manager, \Drupal\Core\Session\AccountInterface $current_user) {
      $this->services = $GetServices;
      $this->sessionVariable = $SessionVariable->get('simple_node_importer');
      $this->sessionManager = $session_manager;
      $this->currentUser = $current_user;
  }

  /**
  * Creates node for specified type of mapped data.
  */
  public static function simpleNodeCreate($records, &$context) {

    $user = "";
    $entity_type = 'node';
    
    foreach ($records as $record) {
      //get user details if exists otherwse create
      if(!empty($record['uid']) && filter_var($record['uid'], FILTER_VALIDATE_EMAIL)){
        $user = \Drupal::service('snp.get_services')->getUserByEmail($record['uid']);
      }
      //assigning user id to node
      if($user && !is_integer($user)){
        $uid = $user->id();
      }
      else{
        $uid = $user;
      }

      $node_data = [
        'type' => $record['type'],
        'title' => !empty($record['title']) ? $record['title'] : ($batch_result['result'][] = $record),
        'uid' => isset($uid) ? $uid : 1,
        'status' => TRUE,
      ];

      $field_names = array_keys($record);
     
      $batch_result = \Drupal::service('snp.get_services')->checkFieldWidget($field_names, $record, $node_data, $entity_type);

      if (!empty($batch_result['result'])) {
        if (!isset($context['results']['failed'])) {
          $context['results']['failed'] = 0;
        }
        $context['results']['failed']++;
        $batch_result->result['sni_id'] = $context['results']['failed'];
        $context['results']['sni_nid'] = $record['nid'];
        $context['results']['data'][] = serialize($batch_result['result']);
      }
      else {
        $node = Node::create($batch_result);
        $node->save();
        if ($node->id()) {
          if (!isset($context['results']['created'])) {
            $context['results']['created'] = 0;
          }
          $context['results']['created']++;
        }
        else {
          $context['results']['failed']++;
          $batch_result['result']['sni_id'] = $context['results']['failed'];
          $context['results']['sni_nid'] = $record['nid'];
          $context['results']['data'] = $batch_result['result'];
        }
      }    
    }    
  }

  /**
  * Callback : Called when batch process is finished.
  */
  function nodeImportBatchFinished($success, $results, $operations) {
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
        //simple_node_importer_add_node_resolution_center($results);
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

  public function simple_node_importer_delete_node($option, $node) {
    if ($node) {
      $storage_handler = \Drupal::entityTypeManager()->getStorage("node");
      $entity = $storage_handler->load($node);
      $storage_handler->delete(array($entity));
      $response = new RedirectResponse('/node/add/simple_node');
      return $response->send();
    }
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

  /**
   * {@inheritdoc}
   */
   public static function create(ContainerInterface $container) {
     return new static(
       $container->get('snp.get_services'),
       $container->get('user.private_tempstore'),
       $container->get('session_manager'),
       $container->get('current_user')
     );
   }

}
