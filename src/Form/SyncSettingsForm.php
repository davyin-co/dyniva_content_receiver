<?php

namespace Drupal\dyniva_content_receiver\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;

class SyncSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dyniva_content_receiver_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('dyniva_content_receiver.settings');

    $form['skipped_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Skipped fields'),
      '#description' => $this->t('Please enter a field name per line.'),
      '#default_value' => $config->get('skipped_fields') ?: 'sync_sites'
    ];

    $form['default_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default fields (YAML)'),
      '#description' => "eg:<br/>node:<br/>&nbsp;&nbsp;article:<br/>&nbsp;&nbsp;&nbsp;&nbsp;category:<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;target_id: 1<br/>&nbsp;&nbsp;&nbsp;&nbsp;field_subtitle:<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;value: 'subtitle content'",
      '#default_value' => $config->get('default_fields') ?: '',
      '#attributes' => [
        'data-action' => 'codemirror-yaml'
      ]
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $default_fields = $form_state->getValue('default_fields');
    if($default_fields) {
      try {
        Yaml::parse($default_fields);
      } catch(\Exception $e) {
        $form_state->setError($form['default_fields'], t($e->getMessage()));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('dyniva_content_receiver.settings');
    $config->set('skipped_fields', $form_state->getValue('skipped_fields'));
    $config->set('default_fields', $form_state->getValue('default_fields'));

    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dyniva_content_receiver.settings',
    ];
  }

}
