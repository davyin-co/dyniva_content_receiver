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

/**
 * Queue.
 */
class Queue {

  public function insert($url, $entity, array $headers = []) {
    if(!$entity) return false;
    $values = [
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'uuid' => $entity->uuid(),
      'status' => 0,
      'domain' => $url,
      'headers' => PhpSerialize::encode($headers),
      'created' => time(),
    ];
    \Drupal::database()
      ->insert('dyniva_content_receiver_request_queue')
      ->fields(array_keys($values),$values)->execute();
    return true;
  }

  public function getItems($limit = 10, $status = 0) {
    $query = \Drupal::database()->select('dyniva_content_receiver_request_queue','n');
    $query->fields('n');
    $query->condition('status', $status);
    $query->range(0, $limit);
    return $query->execute()->fetchAll();
  }

  public function clear() {
    $query = \Drupal::database()->delete('dyniva_content_receiver_request_queue');
    $query->execute();
    return true;
  }

  public function getInfo($id) {
    $query = \Drupal::database()->select('dyniva_content_receiver_request_queue','n');
    $row = $query->fields('n')
      ->condition('id', $id)
      ->condition('status', 0)
      ->execute()
      ->fetchObject();
    $url = $row->domain;
    $headers = [];
    if($row->headers) {
      $headers = PhpSerialize::decode($row->headers);
    }
    if($row->entity_type && $row->uuid) {
      $entity = \Drupal::service('entity.manager')->loadEntityByUuid($row->entity_type, $row->uuid);

      if (!$entity) {
        $entity_type_manager = \Drupal::entityTypeManager()->getStorage($row->entity_type);
        $entity_key = $entity_type_manager->getEntityType()->getKey('id');
        $table_name = $entity_type_manager->getEntityType()->getBaseTable();
        $entity_id = \Drupal::database()->select($table_name, 'n')
          ->condition('n.uuid', $row->uuid)
          ->fields('n', [$entity_key])
          ->execute()
          ->fetchField();
        if ($entity_id) {
          $entity = $entity_type_manager->loadDeleted($entity_id);
        }
      }
    } else {
      $entity = false;
    }
    return [$url, $entity, $headers];
  }

  public function update($id, $data) {
    \Drupal::database()
    ->update('dyniva_content_receiver_request_queue')
    ->fields($data)
    ->condition('id', $id)
    ->execute();
  }
}
