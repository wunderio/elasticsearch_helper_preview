<?php

namespace Drupal\elasticsearch_helper_preview\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\Routing\Route;

/**
 * Converts stored form state into an entity.
 */
class ContentPreviewConverter implements ParamConverterInterface {

  /**
   * The private storage factory instance.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * ContentPreviewConverter constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private storage factory instance.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $store = $this->tempStoreFactory->get('elasticsearch_preview');

    return $store->get($value);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && $definition['type'] == 'elasticsearch_preview';
  }

}
