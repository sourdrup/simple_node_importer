<?php /**
 * @file
 * Contains \Drupal\simple_node_importer\Controller\UserImportController.
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
class UserImportController extends ControllerBase {
protected $services;
protected $sessionVariable;
protected $sessionManager;
protected $currentUser;

public static function UserImport($records, &$context) {
  $entity_type = 'user';
  foreach ($records as $record) {
    //get user details if exists otherwse create
    $batch_result['result'] = [];
    $user_data = [];

    if(empty($record['name'])){
      $batch_result['result'] = $record;
    }

    $user = \Drupal::service('snp.get_services')->getUserByUsername($record['name']);

    if($user){
      $batch_result['result'] = $record;
    }



    $field_names = array_keys($record);

    $user_data = [
      'type' => 'user',
      'mail' => !empty($record['mail']) ? $record['mail'] : '',
      'name' => $record['name'],
      'status' => ($record['status'] == 1 || $record['status'] == TRUE) ? TRUE : FALSE,
      'roles' => $record['roles']
    ];

    if(empty($batch_result['result'])){
      $batch_result = \Drupal::service('snp.get_services')->checkFieldWidget($field_names, $record, $user_data, $entity_type);
    }

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
      $user_data = $batch_result;
      $user_account = User::create($user_data);
      $user_account->save();
      $id = $user_account->id();

      if ($id) {
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
public static function userImportBatchFinished($success, $results, $operations) {
  if ($success) {    
    $rclink =  Link::fromTextAndUrl(t('Resolution Center'), Url::fromRoute('simple_node_importer.node_resolution_center'))->toString();
    $link = $rclink->getGeneratedLink();

    $created_count = !empty($results['created']) ? $results['created'] : NULL;
    $failed_count = !empty($results['failed']) ? $results['failed'] : NULL;

    if ($created_count && !$failed_count) {
      $import_status = t("Users registered successfully: %created_count", array('%created_count' => $created_count));
    }
    elseif(!$created_count && $failed_count) {
      $import_status = t('Users import failed: %failed_count .To view failed records, please visit', array('%failed_count' => $failed_count)) . $link;
    }
    else {
      $import_status = t('Users registered successfully: @created_count.<br/>Users import failed: @failed_count.<br/>To view failed records, please visit ', array('@created_count' => $created_count, '@failed_count' => $failed_count)) . $link;
    }
    if (isset($results['failed']) && !empty($results['failed'])) {
      // Add Failed nodes to Resolution Table.
      \Drupal\simple_node_importer\Controller\NodeImportController::addFailedRecordsInRC($results);
    }

    drupal_set_message(t("Users import completed! Import status:<br/>$import_status"));
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

public static function create(ContainerInterface $container) {
    return new static(
      $container->get('snp.get_services'),
      $container->get('user.private_tempstore'),
      $container->get('session_manager'),
      $container->get('current_user')
    );
  }
}
