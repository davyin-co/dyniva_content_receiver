<?php

namespace Drupal\dyniva_content_receiver\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\default_paragraphs\Plugin\Field\FieldWidget\DefaultParagraphsWidget;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Drupal\Component\Serialization\PhpSerialize;

/**
 * BulkUpdateFieldsForm.
 */
class ActionForm extends FormBase implements FormInterface {

  /**
   * Keep track of user input.
   *
   * @var userInput
   */
  protected $userInput = [];

  /**
   * Tempstorage.
   *
   * @var tempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Session.
   *
   * @var sessionManager
   */
  private $sessionManager;

  /**
   * User.
   *
   * @var currentUser
   */
  private $currentUser;

  /**
   * Constructs a \Drupal\bulk_update_fields\Form\BulkUpdateFieldsForm.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   Temp storage.
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   Session.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   User.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('session_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dyniva_content_receiver_action_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $options = [];
    $sites = _dyniva_content_receiver_get_sites();
    foreach($sites as $site) {
      $value = base64_encode($site['url'].'::'.$site['uuid']);
      $options[$value] = t('Synchronization to'). ' ' . $site['label'];
    }

    $form['site'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Site'),
      '#required' => TRUE,
      '#options' => $options
    );

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Next'),
    ];

    $entities = $this->tempStoreFactory
      ->get('dyniva_content_agent_action_ids')
      ->get($this->currentUser->id());

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /* @var \Drupal\dyniva_content_receiver\Client $client */
    $client = \Drupal::service('dyniva_content_receiver.client');

    $values = $form_state->getValues();
    $sites = $values['site'];

    $entities = $this->tempStoreFactory
      ->get('dyniva_content_agent_action_ids')
      ->get($this->currentUser->id());

    foreach($sites as $site) {
      $value = base64_decode($site);
      list($url, $uuid) = explode('::', $value);
      foreach ($entities as $entity) {
        $client->pushQueue($url, $entity, ['Authorization' => "Uuid " . $uuid]);
      }
    }
    $client->doQueue(65535);
  }

}
