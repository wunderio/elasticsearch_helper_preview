<?php

namespace Drupal\elasticsearch_helper_preview\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\elasticsearch_helper_preview\Preview;
use Drupal\elasticsearch_helper_preview\PreviewHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preview controller class.
 */
class PreviewController extends ControllerBase {

  /**
   * The preview handler instance.
   *
   * @var \Drupal\elasticsearch_helper_preview\PreviewHandler
   */
  protected $previewHandler;

  /**
   * Preview controller class constructor.
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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('elasticsearch_helper_preview.preview_handler')
    );
  }

  /**
   * Returns preview action access result.
   *
   * @param \Drupal\elasticsearch_helper_preview\Preview $preview
   *   The preview instance.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Returns the access result object.
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
   * Returns the redirection response to the preview URL.
   *
   * @param \Drupal\elasticsearch_helper_preview\Preview $preview
   *   The preview instance.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function preview(Preview $preview) {
    // Get base app URL.
    $base_url = $this->previewHandler->getBaseUrl();
    // Metadata needs to be collected before redirecting an Ajax response.
    $preview_path = $preview->getPreviewPath()->toString(TRUE)->getGeneratedUrl();
    // Prepare the response.
    $response = new TrustedRedirectResponse($base_url . $preview_path);
    // Do not cache the redirect response.
    $response->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));

    return $response;
  }

}
