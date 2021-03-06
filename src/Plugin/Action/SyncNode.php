<?php

namespace Drupal\dyniva_content_receiver\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Session\SessionManagerInterface;

/**
 * Synchronization node action
 *
 * @Action(
 *   id = "bluk_sync_node",
 *   label = @Translation("Synchronization to other site"),
 *   type = "node",
 *   confirm_form_route_name = "dyniva_content_receiver.action_form"
 * )
 */
class SyncNode extends ActionBase implements ContainerFactoryPluginInterface {
  /**
   * The plugin_id.
   *
   * @var pluginId
   */
  protected $pluginId;

  /**
   * The plugin implementation definition.
   *
   * @var pluginDefinition
   */
  protected $pluginDefinition;

  /**
   * Configuration information passed into the plugin.
   *
   * When using an interface like
   * \Drupal\Component\Plugin\ConfigurablePluginInterface, this is where the
   * configuration should be stored.
   *
   * Plugin configuration is optional, so plugin implementations must provide
   * their own setters and getters.
   *
   * @var configuration
   */
  protected $configuration;

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
   * The tempstore factory.
   *
   * @var tempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Constructs a BulkUpdateFields object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   The session.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The session.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PrivateTempStoreFactory $temp_store_factory,
    SessionManagerInterface $session_manager,
    AccountInterface $current_user
  ) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('tempstore.private'),
      $container->get('session_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    // TODO: 在vbo下存在bug, 有confirm_form_route_name时不会执行此方法
    $ids = [];
    foreach ($entities as $entity) {
      $ids[$entity->id()] = $entity;
    }
    $this->tempStoreFactory->get('dyniva_content_agent_action_ids')
      ->set($this->currentUser->id(), $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    $this->executeMultiple([$entity]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'node') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }

    // Other entity types may have different
    // access methods and properties.
    return TRUE;
  }

}
