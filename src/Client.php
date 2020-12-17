<?php

namespace Drupal\dyniva_content_receiver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client as GuzzleClient;
use Drupal\Component\Serialization\PhpSerialize;
use Drupal\dyniva_content_sync\ContentSyncHelper;
use Symfony\Component\Yaml\Yaml;

/**
 * Client.
 */
class Client {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;
  protected $module_handler;
  protected $logger;
  protected $skipped_fields = [];

  /**
   * Constructs the TermBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entityManager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entityManager, $module_handler) {
    $this->entityManager = $entityManager;
    $this->module_handler = $module_handler;
    $this->logger = \Drupal::logger('dyniva_content_receiver');

    if(
      \Drupal::moduleHandler()->moduleExists('dyniva_content_agent') &&
      $config = \Drupal::config('dyniva_content_agent.settings')
    ) {
      if($skipped_fields = $config->get('skipped_fields')) {
        $this->skipped_fields = array_map('trim', explode("\n", $skipped_fields));
      }
    }
  }

  public function setSkippedFields(array $skipped_fields) {
    $this->skipped_fields = $skipped_fields;
  }

  /**
   * Restful entity request
   *
   * @param $server_url
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param array $headers
   *
   * @return bool
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function doSyncEntity($server_url, EntityInterface $entity, array $headers = []) {
    if(
      \Drupal::moduleHandler()->moduleExists('dyniva_content_agent') &&
      $config = \Drupal::config('dyniva_content_agent.settings')
    ) {
      $default_fields = $config->get('default_fields');
      if($default_fields) {
        $default_fields = Yaml::parse($default_fields);
        foreach($default_fields as $entity_type => $bundles) {
          if($entity->getEntityTypeId() == $entity_type) {
            foreach($bundles as $bundle => $fields) {
              if($entity->bundle() == $bundle) {
                foreach($fields as $field => $values) {
                  $entity->get($field)->setValue($values);
                }
              }
            }
          }
        }
      }
    }
    $data = ContentSyncHelper::exportEntity($entity, $this->skipped_fields);
    $body = json_encode($data);
    return $this->doSyncJson($server_url, $body, $headers);
  }

  /**
   * Restful json request
   *
   * @param $server_url
   * @param $json
   * @param array $headers
   *
   * @return bool
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function doSyncJson($server_url, $json, array $headers = []) {
    $client = new GuzzleClient();
    $headers['Content-Type'] = 'application/json';
    $request = new Request('POST', $server_url.'/content_sync/post.json', $headers, $json);
    try {
      $response = $client->send($request);
      $this->logger->info("Send to $server_url, headers: ".print_r($headers, true));
    } catch(\GuzzleHttp\Exception\ClientException $e) {
      $this->logger->error($e->getMessage());
      return false;
    }
    if($response->getReasonPhrase() != 'OK') {
      $this->logger->error($response->getStatusCode().': '.$response->getReasonPhrase());
    } else {
      $json = (string)$response->getBody();
      $content = json_decode($json);
      if(!empty($content->message)) {
        $this->logger->error($content->message);
      }
      return $content->status;
    }
    return false;
  }

  /**
   * 指定队列中的一项进行同步
   *
   * @param $id
   *
   * @return bool
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function doSyncQueueItem($id) {
    $queue = \Drupal::service('dyniva_content_receiver.queue');
    list($url, $entity, $headers) = $queue->getInfo($id);

    if($url && $entity) {
      if($this->doSyncEntity($url, $entity, $headers)) {
        $queue->update($id, [
          'changed' => time(),
          'status' => 1
        ]);
        return true;
      } else {
        $queue->update($id, [
          'changed' => time(),
          'status' => 2
        ]);
      }
    } else {
      $queue->update($id, [
        'changed' => time(),
        'status' => 3
      ]);
    }
    return false;
  }

  /**
   * 同步请求压入队列
   */
  public function pushQueue($url, EntityInterface $entity, array $headers = []) {
    if($entity->bundle() == 'site') return false;
    $queue = \Drupal::service('dyniva_content_receiver.queue');
    $queue->insert($url, $entity, $headers);
    return true;
  }

  /**
   * 开始batch
   *
   * @param int $batch_limit
   */
  public function doQueue($batch_limit = 10) {
    // TODO: 开始10（可配置）个转入batch，其余转入队列
    if($batch_limit) {
      $queue = \Drupal::service('dyniva_content_receiver.queue');
      $rows = $queue->getItems($batch_limit);
      $operations = [];
      foreach($rows as $row) {
        $operations[] = ['\Drupal\dyniva_content_receiver\BulkRequest::syncEntity', [$row->id]];
      }
      $batch = [
        'title' => t('Synchronizing...'),
        'operations' => $operations,
        'finished' => '\Drupal\dyniva_content_receiver\BulkRequest::finishedCallback',
        'file' => '\Drupal\dyniva_content_receiver\BulkRequest',
      ];
      batch_set($batch);
    }
  }

}
