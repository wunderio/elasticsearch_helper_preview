<?php

namespace Drupal\elasticsearch_helper_preview\EventSubscriber;

use Drupal\elasticsearch_helper\Event\ElasticsearchEvents;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperationEvent;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperationRequestEvent;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperations;
use Drupal\elasticsearch_helper_preview\PreviewHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Preview event subscriber class.
 */
class PreviewEventSubscriber implements EventSubscriberInterface {

  /**
   * The preview handler instance.
   *
   * @var \Drupal\elasticsearch_helper_preview\PreviewHandler
   */
  protected $previewHandler;

  /**
   * PreviewEventSubscriber constructor.
   *
   * @param \Drupal\elasticsearch_helper_preview\PreviewHandler $preview_handler
   *   The preview handler instance.
   */
  public function __construct(PreviewHandler $preview_handler) {
    $this->previewHandler = $preview_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[ElasticsearchEvents::OPERATION][] = ['onOperation'];
    $events[ElasticsearchEvents::OPERATION_REQUEST][] = ['onOperationRequest'];

    return $events;
  }

  /**
   * Allows indexing of unpublished content for previewing purposes.
   *
   * @param \Drupal\elasticsearch_helper\Event\ElasticsearchOperationEvent $event
   *   The Elasticsearch operation event object.
   */
  public function onOperation(ElasticsearchOperationEvent $event) {
    if ($event->getOperation() == ElasticsearchOperations::DOCUMENT_INDEX) {
      $plugin = $event->getPluginInstance();

      // Check if preview is supported for given index plugin.
      if ($this->previewHandler->getPreviewDefinition($plugin->getPluginId())) {
        // Get entity.
        $entity = &$event->getObject();

        if (!empty($entity->in_preview)) {
          // Allow index operation for previewing purposes.
          $event->allowOperation();
        }
      }
    }
  }

  /**
   * Adds the "refresh" parameter to the document index request.
   *
   * In order to ensure that the index is promptly refreshed after the document
   * is indexed in the preview index, the "refresh" parameter is added to the
   * index query.
   *
   * @param \Drupal\elasticsearch_helper\Event\ElasticsearchOperationRequestEvent $event
   */
  public function onOperationRequest(ElasticsearchOperationRequestEvent $event) {
    $request_wrapper = $event->getRequestWrapper();

    if ($request_wrapper->getOperation() == ElasticsearchOperations::DOCUMENT_INDEX) {
      $plugin = $request_wrapper->getPluginInstance();

      // Check if preview is supported for given index plugin.
      if ($this->previewHandler->getPreviewDefinition($plugin->getPluginId())) {
        // Get the entity.
        $entity = &$request_wrapper->getObject();

        if (!empty($entity->in_preview)) {
          // Add the "refresh" parameter for the index query parameters.
          $callback_parameters = &$event->getCallbackParameters();
          $callback_parameters[0]['refresh'] = 'wait_for';
        }
      }
    }
  }

}
