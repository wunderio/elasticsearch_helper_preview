<?php

namespace Drupal\elasticsearch_helper_preview\EventSubscriber;

use Drupal\elasticsearch_helper\Event\ElasticsearchEvents;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperationEvent;
use Drupal\elasticsearch_helper\Event\ElasticsearchOperations;
use Drupal\elasticsearch_helper_preview\PreviewHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class PreviewEventSubscriber
 */
class PreviewEventSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\elasticsearch_helper_preview\PreviewHandler
   */
  protected $previewHandler;

  /**
   * PreviewEventSubscriber constructor.
   *
   * @param \Drupal\elasticsearch_helper_preview\PreviewHandler $preview_handler
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

    return $events;
  }

  /**
   * Skips indexing and removes existing document for unpublished content.
   *
   * @param \Drupal\elasticsearch_helper\Event\ElasticsearchOperationEvent $event
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

}
