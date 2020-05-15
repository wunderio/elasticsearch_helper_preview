<?php

namespace Drupal\elasticsearch_helper_preview;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Preview instance class.
 */
class Preview {

  /**
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * @var \Drupal\Core\Url
   */
  protected $previewPath;

  /**
   * @var string
   */
  protected $indexName;

  /**
   * @var string|int
   */
  protected $id;

  /**
   * Preview constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param \Drupal\Core\Url $preview_path
   * @param $index_name
   * @param $id
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
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Returns preview path.
   *
   * @return \Drupal\Core\Url
   */
  public function getPreviewPath() {
    return $this->previewPath;
  }

  /**
   * Returns index name.
   *
   * @return string
   */
  public function getIndexName() {
    return $this->indexName;
  }

  /**
   * Returns elasticsearch document ID.
   *
   * @return int|string
   */
  public function getDocumentId() {
    return $this->id;
  }

}
