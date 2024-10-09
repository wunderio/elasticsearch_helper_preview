<?php

namespace Drupal\elasticsearch_helper_preview;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Preview instance class.
 */
class Preview {

  /**
   * The entity being previewed.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The preview URL object.
   *
   * @var \Drupal\Core\Url
   */
  protected $previewPath;

  /**
   * The index name.
   *
   * @var string
   */
  protected $indexName;

  /**
   * The document ID in the index.
   *
   * @var string|int
   */
  protected $id;

  /**
   * Preview constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being previewed.
   * @param \Drupal\Core\Url $preview_path
   *   The preview URL object.
   * @param string $index_name
   *   The index name.
   * @param string $id
   *   The document ID in the index.
   */
  public function __construct(EntityInterface $entity, Url $preview_path, $index_name, $id) {
    $this->entity = $entity;
    $this->previewPath = $preview_path;
    $this->indexName = $index_name;
    $this->id = $id;
  }

  /**
   * Returns entity being previewed.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity being previewed.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Returns preview path.
   *
   * @return \Drupal\Core\Url
   *   The preview URL object.
   */
  public function getPreviewPath() {
    return $this->previewPath;
  }

  /**
   * Returns index name.
   *
   * @return string
   *   The index name.
   */
  public function getIndexName() {
    return $this->indexName;
  }

  /**
   * Returns elasticsearch document ID.
   *
   * @return int|string
   *   The document ID.
   */
  public function getDocumentId() {
    return $this->id;
  }

}
