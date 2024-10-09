<?php

namespace Drupal\elasticsearch_helper_preview\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Elasticsearch Helper Preview settings form.
 */
class ElasticsearchPreviewForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'elasticsearch_helper_preview.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elasticsearch_helper_preview_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('elasticsearch_helper_preview.settings');

    $form['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Frontend application URL'),
      '#description' => $this->t('Enter the URL of your front-end application.'),
      '#default_value' => $config->get('base_url'),
    ];

    $expiration_options = [];
    $time_slots = [120, 180, 300];

    // Create expiration options (in minutes).
    foreach ($time_slots as $seconds) {
      $minutes = $seconds / 60;
      $expiration_options[$seconds] = $this->formatPlural($minutes, 'One minute', '@count minutes');
    }

    $form['expire'] = [
      '#type' => 'select',
      '#title' => $this->t('Expire'),
      '#options' => $expiration_options,
      '#description' => $this->t('Enter the lifespan of a preview index. After selected period expired preview indices will be removed by cron.'),
      '#default_value' => $config->get('expire'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Get base URL.
    $base_url = $form_state->getValue('base_url');

    if (!UrlHelper::isExternal($base_url)) {
      $form_state->setErrorByName('base_url', $this->t('The front-end application URL must be external.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Remove the trailing slash.
    $base_url = rtrim($form_state->getValue('base_url'), '/');

    $this->config('elasticsearch_helper_preview.settings')
      ->set('base_url', $base_url)
      ->set('expire', $form_state->getValue('expire', 120))
      ->save();
  }

}
