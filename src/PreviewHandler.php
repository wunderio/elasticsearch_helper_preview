<?php

namespace Drupal\elasticsearch_helper_preview;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexManager;
use Drupal\elasticsearch_helper_preview\Ajax\DialogPreviewCommand;
use Elastic\Elasticsearch\Client;
use Psr\Log\LoggerInterface;

/**
 * Preview handler class.
 */
class PreviewHandler {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * Defines preview index name prefix.
   */
  const INDEX_PREFIX = 'content-preview-';

  /**
   * The private storage factory instance.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The Elasticsearch index manager instance.
   *
   * @var \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexManager
   */
  protected $elasticsearchIndexManager;

  /**
   * The configuration factory instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The UUID generator instance.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * The Elasticsearch client instance.
   *
   * @var \Elastic\Elasticsearch\Client
   */
  protected $elasticsearchClient;

  /**
   * The logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The preview element ID.
   *
   * @var string
   */
  public $previewElementId = 'preview';

  /**
   * PreviewHandler constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private storage factory instance.
   * @param \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexManager $elasticsearch_index_manager
   *   The Elasticsearch index manager instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory instance.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_generator
   *   The UUID generator instance.
   * @param \Elastic\Elasticsearch\Client $client
   *   The Elasticsearch client instance.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger instance.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, ElasticsearchIndexManager $elasticsearch_index_manager, ConfigFactoryInterface $config_factory, UuidInterface $uuid_generator, Client $client, LoggerInterface $logger) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->elasticsearchIndexManager = $elasticsearch_index_manager;
    $this->configFactory = $config_factory;
    $this->uuidGenerator = $uuid_generator;
    $this->elasticsearchClient = $client;
    $this->logger = $logger;
  }

  /**
   * Returns front-end application base URL.
   *
   * @return array|mixed|null
   *   The base URL.
   */
  public function getBaseUrl() {
    return $this->configFactory->get('elasticsearch_helper_preview.settings')->get('base_url');
  }

  /**
   * Returns preview index expiration delay in seconds.
   *
   * @return int
   *   The expiration delay in seconds.
   */
  public function getExpiration() {
    return $this->configFactory->get('elasticsearch_helper_preview.settings')->get('expire');
  }

  /**
   * Alters the preview button on entity form.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   */
  public function alterForm(&$form, FormStateInterface $form_state) {
    if (!empty($form['actions']['preview']['#access'])) {
      // Get entity from the form state.
      if ($entity = $this->getEntityFromFormState($form, $form_state)) {
        // Check if there are index plugins that support previewing.
        if ($this->getCandidatePreviewDefinitions($entity)) {
          $form['actions']['preview']['#ajax']['callback'] = [
            $this,
            'previewSubmit',
          ];
          // Default preview button works in "default" preview context.
          $form['actions']['preview']['#preview_context'] = PreviewDefinition::CONTEXT_DEFAULT;
        }
      }
    }
  }

  /**
   * Submit handler for preview button.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response instance.
   */
  public function previewSubmit(array &$form, FormStateInterface $form_state) {
    // Get preview context.
    $triggering_element = $form_state->getTriggeringElement();
    $preview_context = $triggering_element['#preview_context'];

    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();

    // Submit the form.
    $form_object->submitForm($form, $form_state);

    // Get the entity.
    $entity = $form_object->getEntity();

    // Get Elasticsearch index plugins that contain preview definitions.
    $candidate_plugins = $this->getCandidatePreviewDefinitions($entity, $preview_context);
    $preview_definition = reset($candidate_plugins);

    try {
      $preview = $this->createPreviewIndex($entity, $preview_definition, $preview_context);
      $storage_key = $this->savePreview($entity, $preview, $preview_definition);

      $content = [
        '#type' => 'container',
        '#id' => $this->previewElementId,
      ];
      $content['preview'] = [
        '#type' => 'html_tag',
        '#tag' => 'iframe',
        '#attributes' => [
          'src' => Url::fromRoute('elasticsearch_helper_preview.preview', ['preview' => $storage_key])->toString(),
        ],
      ];
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      $content = ['#markup' => $this->t('Preview cannot be rendered.')];
    }

    // Attach library that displays dialog preview window.
    $content['#attached']['library'][] = 'elasticsearch_helper_preview/preview';

    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand($entity->label(), $content, ['dialogClass' => 'preview']));
    $response->addCommand(new DialogPreviewCommand('#drupal-modal', ['fullscreen' => TRUE]));

    return $response;
  }

  /**
   * Saves preview instance.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being previewed.
   * @param \Drupal\elasticsearch_helper_preview\Preview $preview
   *   The preview instance.
   * @param \Drupal\elasticsearch_helper_preview\PreviewDefinition $preview_definition
   *   The preview definition.
   *
   * @return string
   *   The key of the private storage containing the preview instance.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function savePreview(EntityInterface $entity, Preview $preview, PreviewDefinition $preview_definition) {
    // Store entity in temp storage.
    $storage = $this->tempStoreFactory->get('elasticsearch_preview');
    $storage_key = $this->getStorageKey($entity, $preview_definition);
    $storage->set($storage_key, $preview);

    return $storage_key;
  }

  /**
   * Returns entity from the form state.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Returns the entity from the form state.
   */
  protected function getEntityFromFormState(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();

    // Get the entity.
    $entity = $form_object->getEntity();

    return $entity ?: NULL;
  }

  /**
   * Returns a key for private storage of a preview instance.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being previewed.
   * @param \Drupal\elasticsearch_helper_preview\PreviewDefinition $preview_definition
   *   The preview definition.
   *
   * @return string|null
   *   Returns the private storage key.
   */
  protected function getStorageKey(EntityInterface $entity, PreviewDefinition $preview_definition) {
    return $entity->uuid();
  }

  /**
   * Returns preview instance.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being previewed.
   * @param \Drupal\elasticsearch_helper_preview\PreviewDefinition $preview_definition
   *   The preview definition.
   * @param string $context
   *   The preview context key.
   *
   * @return \Drupal\elasticsearch_helper_preview\Preview
   *   The preview instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \RuntimeException
   */
  public function createPreviewIndex(EntityInterface $entity, PreviewDefinition $preview_definition, $context = PreviewDefinition::CONTEXT_DEFAULT) {
    $plugin_id = $preview_definition->getPluginId();

    $preview_hash = $this->generatePreviewHash();
    $index_name = $this->getPreviewIndexName($preview_hash);

    // Get plugin instance.
    $plugin_instance = $this->getPreviewIndexPluginInstance($plugin_id, $index_name);

    // Create preview index.
    $plugin_instance->setup();

    // Prepare entity for indexing.
    $this->prepareEntity($entity);

    // Index the entity.
    $plugin_instance->index($entity);

    // @todo Find a proper way to avoid multiple serialization.
    if ($document = $plugin_instance->get($entity)) {
      // Get preview path.
      $preview_path = $preview_definition->getPath($context);
      // Replace placeholders with Elasticsearch document data.
      $preview_path = $this->preparePreviewPath($preview_path, $document);
      // Get Url object.
      $preview_path = $this->getPreviewPathUrl($preview_path);

      // Create preview instance.
      $preview = new Preview($entity, $preview_path, $document['_index'], $document['_id']);

      return $preview;
    }
    else {
      throw new \RuntimeException($this->t('Entity could not be serialized'));
    }
  }

  /**
   * Prepare entity for indexing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being previewed.
   */
  protected function prepareEntity(EntityInterface $entity) {
    // If node is new, assign an arbitrary ID.
    if ($entity->isNew()) {
      // Get ID key of the entity.
      $id_key = $entity->getEntityType()->getKey('id');
      // Negative integer will work with integer and string ID fields in
      // Elasticsearch and will not interfere with existing entities.
      $entity->set($id_key, -1);
    }

    // Indicate that the entity is in preview.
    $entity->in_preview = TRUE;
  }

  /**
   * Replaces placeholders in preview path with document data.
   *
   * @param string $path
   *   The preview path.
   * @param array $document
   *   The Elasticsearch document.
   *
   * @return string
   *   The preview path.
   */
  public function preparePreviewPath($path, array $document) {
    // Allow document values and index to be used in preview path placeholder.
    $placeholders = $document['_source'];
    $placeholders['_index'] = $document['_index'];
    $placeholders['_id'] = $document['_id'];

    // Replace placeholders with data.
    $preview_path = '/' . $this->replacePlaceholders($path, $placeholders);

    // Replace multiple forward slashes with a single slash.
    return preg_replace('/^(\/+)/', '/', $preview_path);
  }

  /**
   * Returns Url object with given path.
   *
   * @param string $path
   *   The preview path.
   *
   * @return \Drupal\Core\Url
   *   The preview Url instance.
   */
  public function getPreviewPathUrl($path) {
    return Url::fromUserInput($path);
  }

  /**
   * Returns preview definition of given Elasticsearch index plugin.
   *
   * @param string $plugin_id
   *   The Elasticsearch index plugin ID.
   *
   * @return \Drupal\elasticsearch_helper_preview\PreviewDefinition|null
   *   The preview definition.
   */
  public function getPreviewDefinition($plugin_id) {
    $result = NULL;

    try {
      $plugin_definition = $this->elasticsearchIndexManager->getDefinition($plugin_id);

      if (isset($plugin_definition['preview'])) {
        $result = new PreviewDefinition($plugin_id, $plugin_definition['preview']);
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }

    return $result;
  }

  /**
   * Returns a list of preview definition instances keyed by index plugin ID.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being previewed.
   * @param string $context
   *   The preview context key.
   *
   * @return \Drupal\elasticsearch_helper_preview\PreviewDefinition[]
   *   The preview definition.
   */
  public function getCandidatePreviewDefinitions(EntityInterface $entity, $context = PreviewDefinition::CONTEXT_DEFAULT) {
    $result = [];

    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    foreach ($this->elasticsearchIndexManager->getDefinitions() as $plugin_id => $definition) {
      // Check if preview is supported.
      if (isset($definition['preview'])) {
        // Check if index plugin supports given entity type.
        $entity_type_supported = isset($definition['entityType']) && $definition['entityType'] == $entity_type;

        if ($entity_type_supported) {
          if ($bundle && isset($definition['bundle']) && $definition['bundle'] != $bundle) {
            continue;
          }

          // Try/catch block prevents invalid preview definitions from being
          // returned.
          try {
            $preview_definition = new PreviewDefinition($plugin_id, $definition['preview']);

            // Match preview context.
            if (in_array($context, $preview_definition->getContexts())) {
              $result[$plugin_id] = $preview_definition;
            }
          }
          catch (\Exception $e) {
            $this->logger->error($e->getMessage());
          }
        }
      }
    }

    return $result;
  }

  /**
   * Returns Elasticsearch index plugin instance with modified index name.
   *
   * @param string $plugin_id
   *   The Elasticsearch index plugin ID.
   * @param string $preview_index_name
   *   The preview index name.
   * @param array $configuration
   *   The Elasticsearch index plugin configuration.
   *
   * @return \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexInterface
   *   The Elasticsearch index plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getPreviewIndexPluginInstance($plugin_id, $preview_index_name, $configuration = []) {
    $plugin_definition = $this->elasticsearchIndexManager->getDefinition($plugin_id);
    // Prepare preview index name.
    $plugin_definition['indexName'] = $preview_index_name;

    // Disable multi-lingual index creation.
    if (isset($plugin_definition['multilingual'])) {
      $plugin_definition['multilingual'] = FALSE;
    }

    // Get plugin class.
    $plugin_class = ContainerFactory::getPluginClass($plugin_id, $plugin_definition);

    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      return $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition);
    }

    // Otherwise, create the plugin directly.
    return new $plugin_class($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Generates preview hash.
   *
   * @return string
   *   The preview hash.
   */
  public function generatePreviewHash() {
    return $this->uuidGenerator->generate();
  }

  /**
   * Returns preview index prefix.
   *
   * @return string
   *   The preview index prefix.
   */
  public function getPreviewIndexPrefix() {
    return Settings::get('elasticsearch_helper_preview_index_prefix', self::INDEX_PREFIX);
  }

  /**
   * Returns preview index name.
   *
   * @param string $hash
   *   The preview hash.
   *
   * @return string
   *   The preview index name.
   */
  public function getPreviewIndexName($hash) {
    // Get index prefix.
    $index_prefix = $this->getPreviewIndexPrefix();

    return sprintf('%s-%s', $index_prefix, $hash);
  }

  /**
   * Replace any placeholders of the form {name} in the given string.
   *
   * This is a copy of \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexBase::replacePlaceholders()
   * which (unfortunately) is a private method.
   *
   * @param string $haystack
   *   The placeholder token.
   * @param array $data
   *   The index-able data.
   *
   * @return string
   *   The string with replaced placeholders.
   */
  public function replacePlaceholders($haystack, $data) {
    // Replace any placeholders with the right value.
    $matches = [];

    if (preg_match_all('/{[_\-\w\d]*}/', $haystack, $matches)) {
      foreach ($matches[0] as $match) {
        $key = substr($match, 1, -1);
        $haystack = str_replace($match, $data[$key], $haystack);
      }
    }

    return $haystack;
  }

  /**
   * Cleans up expired preview indices.
   *
   * This method is called automatically on cron run.
   */
  public function garbageCollection() {
    // Get expired indices.
    if ($expired_indices = $this->getExpiredPreviewIndices()) {
      // Split indices into smaller chunks to avoid HTTP line
      // length exceed errors.
      $expired_indices = array_chunk($expired_indices, 10);

      foreach ($expired_indices as $index_chunk) {
        $params = [
          'index' => implode(',', $index_chunk),
        ];

        // Delete expired indices.
        $this->elasticsearchClient->indices()->delete($params);
      }
    }
  }

  /**
   * Returns a list of expired indices.
   *
   * @return array
   *   The list of expired indices.
   */
  protected function getExpiredPreviewIndices() {
    $expired_indices = [];

    // Get expiration timestamp.
    $expiration_timestamp = \Drupal::time()->getRequestTime() - $this->getExpiration();
    // Get all preview indices.
    $indices = $this->getPreviewIndices();

    foreach ($indices as $index) {
      $index_created = strtotime($index['creation.date.string']);

      if ($index_created <= $expiration_timestamp) {
        $expired_indices[] = $index['i'];
      }
    }

    return $expired_indices;
  }

  /**
   * Returns an array of all preview indices.
   *
   * @return array
   *   The list of preview indices.
   */
  protected function getPreviewIndices() {
    // Get preview indices sorted by date.
    $params = [
      'index' => $this->getPreviewIndexPrefix() . '*',
      'h' => 'i,creation.date.string',
      's' => 'creation.date.string',
      'format' => 'json',
    ];

    return $this->elasticsearchClient->cat()->indices($params)->asArray();
  }

}
