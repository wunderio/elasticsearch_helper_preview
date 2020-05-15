(function ($, Drupal) {

  'use strict';

  /**
   * Command to set additional options for preview in a modal dialog.
   */
  Drupal.AjaxCommands.prototype.dialog_preview = function (ajax, response, status) {
    var options_override = {};
    response.settings = response.settings || {};

    if (response.settings.fullscreen) {
      var margin = 40;
      options_override.width = $(window).width() - margin;
      options_override.height = $(window).height() - margin;
    }

    if (options_override) {
      $(response.selector).dialog('option', options_override);
    }

    // Remove padding.
    $(response.selector).css('padding', 0);
    // Set iframe height size to the side of the dialog.
    $(response.selector + ' iframe').css({
      'width': $(response.selector).innerWidth(),
      'height': $(response.selector).innerHeight()
    });
  }

})(jQuery, Drupal);
