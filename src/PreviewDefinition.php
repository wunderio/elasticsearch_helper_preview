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
 *
 */
class PreviewDefinition {

  /**
   * Defines default preview context.
   */
  const CONTEXT_DEFAULT = 'default';

  /**
   * @var string
   */
  protected $pluginId;

  /**
   * @var array
   */
  protected $definition = [];

  /**
   * PreviewDefinition constructor.
   *
   * @param $plugin_id
   * @param array $definition
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
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * Returns preview definition array.
   *
   * @return array
   */
  public function getDefinition() {
    return $this->definition;
  }

  /**
   * Returns available preview contexts.
   *
   * @return array
   */
  public function getContexts() {
    return array_keys($this->definition);
  }

  /**
   * Returns preview path for given context.
   *
   * @param string $context
   *
   * @return mixed|null
   */
  public function getPath($context = self::CONTEXT_DEFAULT) {
    if (isset($this->definition[$context]['path'])) {
      return $this->definition[$context]['path'];
    }

    return NULL;
  }

}
