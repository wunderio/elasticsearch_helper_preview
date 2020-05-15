<?php

namespace Drupal\elasticsearch_helper_preview\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\elasticsearch_helper_preview\Preview;
use Drupal\elasticsearch_helper_preview\PreviewHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PreviewController
 */
class PreviewController extends ControllerBase {

  /**
   * @var \Drupal\elasticsearch_helper_preview\PreviewHandler
   */
  protected $previewHandler;

  /**
   * PreviewController constructor.
   *
   * @param \Drupal\elasticsearch_helper_preview\PreviewHandler $preview_handler
   */
  public function __construct(PreviewHandler $preview_handler) {
    $this->previewHandler = $preview_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('elasticsearch_helper_preview.preview_handler')
    );
  }

  /**
   * @param \Drupal\elasticsearch_helper_preview\Preview $preview
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   */
  public function access(Preview $preview) {
    if ($entity = $preview->getEntity()) {
      if ($entity instanceof EntityInterface) {
        return AccessResult::allowedIf($entity->access('update'));
      }
    }

    return AccessResult::forbidden();
  }

  /**
   * @param \Drupal\elasticsearch_helper_preview\Preview $preview
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function preview(Preview $preview) {
    // Get base app URL.
    $base_url = $this->previewHandler->getBaseUrl();
    // Metadata needs to be collected before redirecting an Ajax response.
    $preview_path = $preview->getPreviewPath()->toString(TRUE)->getGeneratedUrl();

    return new TrustedRedirectResponse($base_url . $preview_path);
  }

}
