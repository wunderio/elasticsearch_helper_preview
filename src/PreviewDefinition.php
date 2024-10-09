<?php

namespace Drupal\elasticsearch_helper_preview;

/**
 * Contains preview definition.
 *
 * Elasticsearch index plugins which support preview should have "preview"
 * property with the following schema:
 *
 * "preview" => [
 *   [preview context] => [
 *     "path" => "[path]"
 *   ]
 * ]
 *
 * Preview context key can describe the meaning of the preview url,
 * e.g., "default" or "landing-page", depending on how the index is used in the
 * front-end application.
 */
class PreviewDefinition {

  /**
   * Defines default preview context.
   */
  const CONTEXT_DEFAULT = 'default';

  /**
   * The Elasticsearch index plugin ID.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The preview definition.
   *
   * @var array
   */
  protected $definition = [];

  /**
   * The preview definition class constructor.
   *
   * @param string $plugin_id
   *   The Elasticsearch index plugin ID.
   * @param array $definition
   *   The preview definition.
   */
  public function __construct($plugin_id, array $definition) {
    $this->validate($definition);

    $this->pluginId = $plugin_id;
    $this->definition = $definition;
  }

  /**
   * Validates provided preview definition.
   *
   * @param array $definition
   *   The preview definition.
   *
   * @throws \InvalidArgumentException
   */
  public function validate(array $definition) {
    if (empty($definition)) {
      throw new \InvalidArgumentException(t('Preview definition is empty.'));
    }
    else {
      foreach ($definition as $context => $settings) {
        $required_fields = ['path'];

        foreach ($required_fields as $field) {
          if (!isset($settings[$field])) {
            $t_args = ['@context' => $context, '@field' => $field];
            throw new \InvalidArgumentException(t('Field "@field" is missing in preview context "@context".', $t_args));
          }
        }
      }
    }
  }

  /**
   * Returns Elasticsearch index plugin ID.
   *
   * @return string
   *   The Elasticsearch index plugin ID.
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * Returns preview definition array.
   *
   * @return array
   *   The preview definition.
   */
  public function getDefinition() {
    return $this->definition;
  }

  /**
   * Returns available preview contexts.
   *
   * @return array
   *   A list of context keys.
   */
  public function getContexts() {
    return array_keys($this->definition);
  }

  /**
   * Returns preview path for given context.
   *
   * @param string $context
   *   The preview context key.
   *
   * @return mixed|null
   *   The preview path URL.
   */
  public function getPath($context = self::CONTEXT_DEFAULT) {
    if (isset($this->definition[$context]['path'])) {
      return $this->definition[$context]['path'];
    }

    return NULL;
  }

}
