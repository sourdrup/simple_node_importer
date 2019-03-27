<?php /**
 * @file
 * Contains \Drupal\simple_node_importer\Controller\DefaultController.
 */

namespace Drupal\simple_node_importer\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Routing;
use Drupal\Core\Session;
use Drupal\Core\Entity;
use Drupal\Core\Link;
use Drupal\Core\Url;
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
        'status' => ($record['status'] == 1 || $record['status'] == TRUE) ? TRUE : FALSE,
      ];

      $field_names = array_keys($record);
     
      $batch_result = \Drupal::service('snp.get_services')->checkFieldWidget($field_names, $record, $node_data, $entity_type);

      if (!empty($batch_result['result'])) {
        if (!isset($context['results']['failed'])) {
          $context['results']['failed'] = 0;
        }
        $context['results']['failed']++;
        $batch_result['result']['sni_id'] = $context['results']['failed'];
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
  public static function nodeImportBatchFinished($success, $results, $operations) {
    if ($success) {
      
      $rclink =  Link::fromTextAndUrl(t('Resolution Center'), Url::fromRoute('simple_node_importer.node_resolution_center'))->toString();
      $link = $rclink->getGeneratedLink();

      $created_count = !empty($results['created']) ? $results['created'] : NULL;
      $failed_count = !empty($results['failed']) ? $results['failed'] : NULL;

      if ($created_count && !$failed_count) {
        $import_status = t("Nodes successfully created: %created_count", array('%created_count' => $created_count));
      }
      elseif(!$created_count && $failed_count) {
        $import_status = t('Nodes import failed: %failed_count .To view failed records, please visit', array('%failed_count' => $failed_count)) . $link;
      }
      else {
        $import_status = t('Nodes successfully created: @created_count.<br/>Nodes import failed: @failed_count.<br/>To view failed records, please visit ', array('@created_count' => $created_count, '@failed_count' => $failed_count)) . $link;
      }
      if (isset($results['failed']) && !empty($results['failed'])) {
        // Add Failed nodes to Resolution Table.
        \Drupal\simple_node_importer\Controller\NodeImportController::addFailedRecordsInRC($results);
      }

      drupal_set_message(t("Node import completed! Import status:<br/>$import_status"));
    }
    else {
      $error_operation = reset($operations);
      $message = t('An error occurred while processing %error_operation with arguments: @arguments', array(
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ));
      drupal_set_message($message, 'error');
    }

    return new RedirectResponse(\Drupal::url('<front>'));
  }

 /**
 * Add data to node resolution table.
 */
  public static function addFailedRecordsInRC($result) {
    if (isset($result['data']) && !empty($result['data'])) {

      $import_status = array(
        'success' => !empty($result['created']) ? $result['created'] : "",
        'fail' => !empty($result['failed']) ? $result['failed'] : "",
      );
      $sni_nid = !empty($result['sni_nid']) ? $result['sni_nid'] : NULL;
      foreach ($result['data'] as $data) {
        $conn = \Drupal\Core\Database\Database::getConnection();
        $resolution_log = $conn->insert('node_resolution')->fields(
          array(
            'sni_nid' => $sni_nid,
            'data' => $data,
            'reference' => \Drupal::service('snp.get_services')->generateReference(10),
            'status' => serialize($import_status),
            'created' => REQUEST_TIME,
          )
        )->execute();
      }

      if ($resolution_log) {
        drupal_set_message(t('Failed node added to resolution center.'));
      }
    }
  }

  public function viewResolutionCenter() {
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
    $connection = \Drupal\Core\Database\Database::getConnection();
    $connection->query("SET SQL_MODE=''");
    $query_record = $connection->select('node_field_data', 'n');
    $query_record->innerJoin('node_resolution', 'nr', 'n.nid = nr.sni_nid');
    $query_record->fields('n', ['nid', 'uid', 'type', 'created']);
    $query_record->fields('nr', ['sni_nid', 'data', 'reference', 'status', 'created', 'changed']);
    $query_record->groupBy('nr.sni_nid');

    $result = $query_record->execute()->fetchAll();

    foreach ($result as $data) {
      $serializData = unserialize($data->data);
      $contentType = $serializData['type'];
      $row = [];
      $row[] = ['data' => $srno];

      // get the bundle label
      $node = \Drupal::entityManager()->getStorage('node')->load($data->nid);
      $bundle_label = \Drupal::entityTypeManager()->getStorage('node_type')->load($contentType)->label();

      $row[] = ['data' => $bundle_label];

      // Convert timestamp to date & time.
      $formatted_date = date('d-M-Y', $data->created);
      $row[] = ['data' => $formatted_date];
      $status = unserialize($data->status);
      $row[] = ['data' => $status['success']];
      $row[] = ['data' => $status['fail']];
      $account = \Drupal\user\Entity\User::load($data->uid); // pass your uid
      $author = $account->getUsername();
      $row[] = ['data' => $author];

      // generate download csv link
      $generateDownloadLink =  Link::fromTextAndUrl(t('DownloadCSV'), Url::fromRoute('simple_node_importer.resolution_center_operations', array('node' => $data->nid, 'op' => 'download-csv')))->toString();
      $csvLink = $generateDownloadLink->getGeneratedLink();

      // generate delete node link
      $url = Url::fromRoute('entity.node.delete_form', ['node' => $data->nid]);
      $generateDeleteLink = Link::fromTextAndUrl('Delete', $url)->toString();
      $deleteLink = $generateDeleteLink->getGeneratedLink();  

      //generate view records link   
      $generateViewLink =  Link::fromTextAndUrl(t('View'), Url::fromRoute('simple_node_importer.resolution_center_operations', array('node' => $data->nid, 'op' => 'view-records')))->toString();
      $viewLink = $generateViewLink->getGeneratedLink(); 
     
      $row[] = array(
        'data' => t($csvLink .' | '. $viewLink .' | '. $deleteLink)
       );
         
      $srno++;
      $rows[] = ['data' => $row];
    }
   
    if(!empty($rows)){
      $output = array(
        '#type' => 'table',
        '#header' => $tableheader,
        '#rows' => $rows,
      );
    }
    else{
      $output = array(
        '#type' => 'table',
        '#header' => $tableheader,
        '#empty' => t('There are no items yet. <a href="@add-url">Add an item.</a>', array(
      '@add-url' => Url::fromRoute('node.add', array('node_type' => 'simple_node'))->toString()))
      );
    }

    return $output;
  }

  public function resolutionCenterOperations(\Drupal\node\NodeInterface $node, $op) {

    $failed_rows = \Drupal\simple_node_importer\Controller\NodeImportController::getFailedRowsInRC($node->id(), NULL);   

    if ($failed_rows) {
      $i = 1;
      foreach ($failed_rows as $col_val) {
        foreach ($col_val as $keycol => $keyfieldval) {
          if($keycol == 'reference' && !empty($col_val[$keycol])){

            $referenceKey = $keyfieldval;
            unset($col_val[$keycol]);

          }
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

    if ($op == 'download-csv'){
      $filename = 'Import-failed-nodes.csv';
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header('Content-Description: File Transfer');
      header("Content-type: text/csv");
      header("Content-Disposition: attachment; filename={$filename}");
      header("Expires: 0");
      header("Pragma: public");
      $fh = @fopen('php://output', 'w');

      // Make sure nothing else is sent, our file is done.
      $header_update = FALSE;
      foreach ($rows as $val) {
        if(!empty($val['type'])){
          unset($val['type']);
        }
        foreach ($val as $key => $keyval) {
          if (!$header_update) {
            $headcol[] = ucwords(str_replace("field ", "", str_replace("_", " ", $key)));
          }
          $row[] = $keyval;
        }

        if (!$header_update) {
          fputcsv($fh, $headcol);
          $header_update = TRUE;
        }

        fputcsv($fh, $row);
      }

      fclose($fh);
      exit();
    }
    else if($op == 'view-records'){
      $srno = 1;
      $tableheader = [
        ['data' => t('Sr no')],
        ['data' => t('Title')],
        ['data' => t('Operations')],
      ];

      foreach ($rows as $val) {
        foreach ($val as $key => $keyval) {
          if($key == 'title'){
            $row[] = ['data' => $srno];
            $row[] = ['data' => $keyval];
          }          
        }        

        // generate add node link
        $generateAddLink =  Link::fromTextAndUrl(t('Edit & Save'), Url::fromRoute('node.add', array('node_type' => $val['type'], 'refkey' => $referenceKey, 'bundle' => $val['type'])))->toString();
        $addLink = $generateAddLink->getGeneratedLink();
        
        $row[] = array(
          'data' => t($addLink)
        );

        $failedRows[] = ['data' => $row];
        $srno++;
      }

      // output as table format
      $output = array(
        '#type' => 'table',
        '#header' => $tableheader,
        '#rows' => $failedRows,
      );

      return $output;
    }    
  } 

  public function snpDeleteNode($option, $node) {
    if ($node) {
      $storage_handler = \Drupal::entityTypeManager()->getStorage("node");
      $entity = $storage_handler->load($node);
      $storage_handler->delete(array($entity));
      $response = new RedirectResponse('/node/add/simple_node');
      return $response->send();
    }
  }

  /**
   * Function to fetch failed nodes from node_resolution table.
   *
   * @param int $nid
   *   Failed nodes from import node nid.
   */
  public static function getFailedRowsInRC($nid = NULL, $refKey = NULL) {
   
    $data = array();

    // Query to fetch failed data.
    $connection = \Drupal\Core\Database\Database::getConnection();
    $connection->query("SET SQL_MODE=''");
    $query_record = $connection->select('node_resolution', 'nr');
    $query_record->fields('nr', ['data', 'reference']);
    
    if(!empty($nid)){
      $query_record->condition('nr.sni_nid', $nid);
      $query_record->addExpression('MAX(nr.serid)');
    }
    
    if(!empty($refKey)){
      $query_record->condition('nr.reference', $refKey);
    }
        
    $result = $query_record->execute()->fetchAll();
    foreach ($result as $k => $value) {
      // code...
      $data[$k] = unserialize($value->data);
      $reference[$k] = $value->reference;
      unset($data[$k]['sni_id']);
    }

    foreach ($data as $rowKey => $rows) {
      if(!empty($rows['nid']) || !empty($rows['type'])){
        unset($rows['nid']);
        //unset($rows['type']);
      }
      foreach ($rows as $key => $record) {
        $records[$rowKey][$key] = $record;
        $records[$rowKey]['reference'] = $reference[$rowKey];
      }
    }

    if (!empty($records)) {
      return $records;
    }
    else {
      return FALSE;
    }
  }

  public function resolution_center_title(\Drupal\node\NodeInterface $node, $op){
    if($op == 'view-records'){
      return 'Resolution Center - View records';
    }
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
