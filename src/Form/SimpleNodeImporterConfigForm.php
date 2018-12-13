<?php

/**
 * @file
 * Contains \Drupal\simple_node_importer\Form\SimpleNodeImporterConfigForm.
 */

namespace Drupal\simple_node_importer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\simple_node_importer\Services\GetContentTypes;

class SimpleNodeImporterConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_node_importer_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simple_node_importer.settings'];
  }


  /**
   * Drupal\simple_node_importer\Services\GetContentTypes.
   */
  protected $content_types;

  /**
   * @var array $content_types
   *   The information from the GetContentTypes service for this form.
   */
  public function __construct($content_types, $checkAvailablity) {
    $this->content_types = $content_types;
    $this->checkAvailablity = $checkAvailablity;
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {

    $config = $this->config('simple_node_importer.settings');

    $content_type_selected = [];
    // @FIXME
    // // @FIXME
    // // This looks like another module's variable. You'll need to rewrite this call
    // // to ensure that it uses the correct configuration object.
    $content_type_select = $config->get('content_type_select');



    if (!empty($content_type_select)) {
      foreach ($content_type_select as $key => $value) {
        if ($value) {
          $content_type_selected[$key] = $value;
        }
      }
    }
    
    $form['fieldset_content_type'] = [
      '#type' => 'fieldset',
      '#title' => t('Content type settings'),
      '#weight' => 1,
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['fieldset_content_type']['content_type_select'] = [
      '#type' => 'checkboxes',
      '#title' => t('Select content type'),
      '#default_value' => isset($content_type_selected) ? $content_type_selected : '',
      '#options' => $this->content_types,
      '#description' => t('Configuration for the content type to be selected.'),
      '#required' => FALSE,
    ];

    $form['fieldset_taxonomy_term_type'] = [
      '#type' => 'fieldset',
      '#title' => t('Taxonomy Term settings'),
      '#weight' => 1,
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['fieldset_taxonomy_term_type']['simple_node_importer_allow_add_term'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow adding new taxonomy terms.'),
      '#default_value' => $config->get('simple_node_importer_allow_add_term'),
      '#description' => t('Check to allow adding term for taxonomy reference fields, if term is not available.'),
    ];

    $form['fieldset_remove_importer'] = [
      '#type' => 'fieldset',
      '#title' => t('Node remove settings'),
      '#weight' => 2,
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    // The options to display in our checkboxes.
    $option = [
      'option' => t('Delete import logs.')
    ];

    $form['fieldset_remove_importer']['node_delete'] = [
      '#title' => '',
      '#type' => 'checkboxes',
      '#description' => t('Select the checkbox to delete all import logs permanently.'),
      '#options' => $option,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('simple_node_importer.settings');
   
    $config->set('content_type_select', $form_state->getValue('content_type_select'))
    ->set('simple_node_importer_allow_add_term', $form_state->getValue('simple_node_importer_allow_add_term'))
    ->set('node_delete', $form_state->getValue('node_delete'))->save();
          

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  public function _submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {

    if ($this->checkAvailablity){
      $node_setting = $form_state->getValue(['node_delete', 'option']);

      $query = db_select('node', 'n');
      $query->fields('n', ['nid']);
      $query->condition('n.status', '1', '=');
      $query->condition('n.type', 'simple_node');
      $query->orderBy('created', 'DESC');

      $nids = $query->execute()->fetchCol();

      if ($node_setting === 'option' && !empty($nids)) {
        entity_delete_multiple('node', $nids);
        drupal_set_message(t('%count nodes has been deleted.', ['%count' => count($nids)]));
      }
    }    
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      //this is not the only way to write this code. You may want to save the Service here instead of the string.
      $container->get('snp.get_content_types')->getContentTypeList(),
      // to check the availability of Content Type exists or not
      $container->get('snp.get_content_types')->checkAvailablity()
    );
  }


}
?>
