<?php /**
 * @file
 * Contains \Drupal\simple_node_importer\Controller\UserImportController.
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
class UserImportController extends ControllerBase {
protected $services;
protected $sessionVariable;
protected $sessionManager;
protected $currentUser;
public function UserImport($records) {
    $entity_type = 'user';
    
    foreach ($records as $record) {
      //get user details if exists otherwse create
      if(!empty($record['mail'])){
            $userObj = user_load_by_mail($record['mail']);
                if($userObj){
                    drupal_set_message('user already exist');
                }
                else{           
                  $node_data = [
                    'type' => 'user',
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
                    $user_data = $record;
                    foreach($user_data as $key => $value){       
                      if($key == 'nid' || $key == 'type'){
                          unset($user_data[$key]); 
                      }
                    }
                    $account = entity_create('user',$user_data);
                    $account->save();
                    $id = $account->id();
                    dsm($id);
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
            else{
                drupal_set_message('mail is required field exist');
            }    
    }    
    // foreach($records as $record){
    //     if(!empty($record['mail'])){
    //     $userObj = user_load_by_mail($record['mail']);
    //         if($userObj){
    //             drupal_set_message('user already exist');
    //         }
    //         else{           
    //             $account = entity_create('user',$record);
    //             $account->save();
    //         }
    //     }
    //     else{
    //         drupal_set_message('mail is required field exist');
    //     }
    //     }
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
