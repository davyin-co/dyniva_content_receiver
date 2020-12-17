<?php

namespace Drupal\dyniva_content_receiver\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\dyniva_content_sync\ContentSyncHelper;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

class APIController extends ControllerBase {

  public function post( Request $request ) {
    return $this->handleJson($request);
  }

  public function test(Request $request) {
    $response['Content-Type'] = $request->headers->get( 'Content-Type' );
    $response['auth'] = \Drupal::currentUser()->id();
    $response['time'] = time();
    return new JsonResponse( $response );
  }

  private function handleJson(Request $request) {
    $data = json_decode( $request->getContent(), TRUE );
//    $request->request->replace( is_array( $data ) ? $data : [] );
    $response = [];
    $response['auth'] = \Drupal::currentUser()->id();
    $response['time'] = time();

    if(!$data) {
      $response['status'] = false;
      $response['message'] = $this->t('Data is required.');
    } else {
      $response['message'] = '';
      $bluk_docs = ContentSyncHelper::importDocs($data);
      $response['status'] = true;
      if($bluk_docs) {
        foreach ($bluk_docs->getResult() as $result) {
          if (isset($result['error'])) {
            $response['message'] .= $result['error']."\n";
            $response['status'] = false;
          }
        }
      }else {
        $response['message'] = $this->t("Import content failure.");
        $response['status'] = false;
      }
    }

    return new JsonResponse( $response );
  }

}
