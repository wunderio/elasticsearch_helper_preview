<?php

namespace Drupal\elasticsearch_helper_preview\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Provides an AJAX command for previewing in a dialog.
 */
class DialogPreviewCommand implements CommandInterface {

  /**
   * The CSS selector.
   *
   * @var string
   */
  protected $selector;

  /**
   * The settings array.
   *
   * @var array|null
   */
  protected $settings;

  /**
   * DialogPreviewCommand constructor.
   *
   * @param string $selector
   *   The CSS selector.
   * @param array|null $settings
   *   The settings array.
   */
  public function __construct($selector, array $settings = NULL) {
    $this->selector = $selector;
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'dialog_preview',
      'selector' => $this->selector,
      'settings' => $this->settings,
    ];
  }

}
