<?php

namespace Drupal\dyniva_content_receiver;

/**
 * BulkRequest.
 */
class BulkRequest {

  /**
   * {@inheritdoc}
   */
  public static function syncEntity($id, &$context) {

    if (empty($context['results'])) {
      $context['results']['counter'] = [
        '@numitems' => 0,
        '@failures' => 0,
      ];
    }

    $message = 'Sync on ';

    // Load data from database
    $queue = \Drupal::service('dyniva_content_receiver.queue');
    $client = \Drupal::service('dyniva_content_receiver.client');

    list($url, $entity, $headers) = $queue->getInfo($id);
    if(!$entity) {
      // Clean deleted entity queue item.
      $queue->update($id, [
        'changed' => time(),
        'status' => 4
      ]);
    }
    if($client->doSyncQueueItem($id)) {
      $context['results'][] = $entity->id() . ' : ' . $entity->label();
      $context['results']['results_entities'][] = $entity->id();
      $context['message'] = $message . $entity->label();
    } else {
      $context['results']['counter']['@failures'] = $context['results']['counter']['@failures'] + 1;
    }
    $context['results']['counter']['@numitems'] = $context['results']['counter']['@numitems'] + 1;
  }

  /**
   * @param $label
   * @param $url
   * @param $json
   * @param $headers
   * @param $context
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public static function syncJson($label, $url, $json, $headers, &$context) {

    if (empty($context['results'])) {
      $context['results']['counter'] = [
        '@numitems' => 0,
        '@failures' => 0,
        '@name' => 'Site Json Content Sync',
        '@id' => 'json_content_sync',
      ];
    }

    /* @var \Drupal\dyniva_content_receiver\Client $client */
    $client = \Drupal::service('dyniva_content_receiver.client');
    try {
      if($client->doSyncJson($url, $json, $headers)) {
        $context['results']['results_entities'][] = $label;
        $context['message'] = $label . t(' has been synchronized.');
      } else {
        $context['results']['results_failures'][] = $label;
        $context['results']['counter']['@failures'] = $context['results']['counter']['@failures'] + 1;
      }
    } catch(\Exception $e) {
      $context['results']['results_failures'][] = $label;
      $context['results']['counter']['@failures'] = $context['results']['counter']['@failures'] + 1;
    }

    $context['results']['counter']['@numitems'] = $context['results']['counter']['@numitems'] + 1;
  }

  /**
   * {@inheritdoc}
   */
  public static function finishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message_entity = \Drupal::translation()->formatPlural(
        isset($results['results_entities'])?count($results['results_entities']):0,
        'One entity', '@count entities'
      );
      $message = $message_entity.' '.t('finished');
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);

    if($results['counter']['@failures'] > 0 && !empty($results['results_failures'])) {
      \Drupal::messenger()->addError(t('@labels synchronization failure.', [
        '@labels' => implode(', ', $results['results_failures'])
      ]));
    }
  }

}
