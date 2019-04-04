<?php

namespace Drupal\simple_node_importer\Form;

use Drupal\simple_node_importer\Controller\NodeImportController;
use Drupal\Core\Form\ConfirmFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing;
use Drupal\Core\Session;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines a confirmation form to confirm deletion of something by id.
 */
class SimpleUserConfirmImportForm extends ConfirmFormBase {

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
public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state,$option = NULL, \Drupal\node\NodeInterface $node = NULL) {
	$this->node = $node;
	$form['snp_nid'] = [
		'#type' => 'hidden',
		'#value' => $node->id()
	];
	$parameters = array('option' => $option,'node' =>$node->id());
	return parent::buildForm($form, $form_state,$parameters);
}

/**
* {@inheritdoc}
*/
public function submitForm(array &$form, FormStateInterface $form_state) {
	// Remove unnecessary values.
	$form_state->cleanValues();

	$node_storage = $this->entityTypeManager->getStorage('node');
	$file_storage = $this->entityTypeManager->getStorage('file');

	$snp_nid = $form_state->getValue('snp_nid');

	$node = $node_storage->load($snp_nid);

	$operations = [];
	$map_values = $this->sessionVariable->get('mapvalues');
	$fid = $node->get('field_upload_csv')->getValue()[0]['target_id'];
	$file = $file_storage->load($fid);
	$csv_uri = $file->getFileUri();
	$handle = fopen($csv_uri, 'r');
	$columns = [];
	$columns = array_values($this->services->simple_node_importer_getallcolumnheaders($csv_uri));
	$record = [];
	$map_fields = array_keys($map_values);
	$i = 1;
	$records = array();
	

	while ($row = fgetcsv($handle)) {
	  if ($i == 1) {
		$i++;
	    continue;
		}

	  foreach ($row as $k => $field) {
	    $column1 = str_replace(' ', '_', strtolower($columns[$k]));
	    foreach ($map_fields as $field_name) {
	      if ($map_values[$field_name] == $column1) {
					$record[$field_name] = trim($field);
	      }
	      else {
					
					if (is_array($map_values[$field_name])) {
						$multiple_fields = array_keys($map_values[$field_name]);

	          foreach ($multiple_fields as $j => $m_fields) {
				      if ($m_fields == $column1) {
								if($m_fields == 'roles'){
									$field = str_replace(' ', '_', strtolower($field));
								}
								if(!empty($field)){
									$record[$field_name][$j] = trim($field);
								}
								else{
									$record[$field_name][$j] = NULL;
								}
							}
	          }
	        }
	      }
	    }
	  }
	  $record['nid'] = $node->id();
	  $record['type'] = 'user';
	  $records[] = $record;
	}

	// Preapring batch parmeters to be execute.
	$batch = [
	  'title' => t('Importing User'),
	  'operations' => [
	      [
	        '\Drupal\simple_node_importer\Controller\UserImportController::UserImport',
	        [$records],
	      ],
	   ],
	  'finished' => '\Drupal\simple_node_importer\Controller\UserImportController::userImportBatchFinished',
	  'init_message' => t('Initialializing User importing.'),
	  'progress_message' => t('Processed @current out of @total.'),
	  'error_message' => t('User creation has encountered an error.'),
	];
	// Set the batch operation.
	batch_set($batch);
	fclose($handle);
}

/**
* {@inheritdoc}
*/
public function getFormId() : string {
return "simple_user_confirm_importing_form";
}

/**
* {@inheritdoc}
*/
public function getCancelUrl() {
$nid = $this->node->id();
$parameters = $this->sessionVariable->get('parameters');
return new Url('simple_node_importer.user_mapping_form', $parameters);
}

/**
* {@inheritdoc}
*/
public function getQuestion() {

$critical_info = "<p class='confirmation-info'></p><p>Do you want to continue?</p>";

return t($critical_info);
}

/**
* {@inheritdoc}
*/
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
