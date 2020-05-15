<?php

namespace Drupal\elasticsearch_helper_preview\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Provides an AJAX command for previewing in a dialog.
 */
class DialogPreviewCommand implements CommandInterface {

  /**
   * @var string
   */
  protected $selector;

  /**
   * @var array|null
   */
  protected $settings;

  /**
   * DialogPreviewCommand constructor.
   *
   * @param $selector
   * @param array|NULL $settings
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
