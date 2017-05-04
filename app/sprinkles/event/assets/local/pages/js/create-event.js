/**
 * Page-specific Javascript file.  Should generally be included as a separate asset bundle in your page template.
 * example: {{ assets.js('js/pages/create-event') | raw }}
 *
 * This script depends on validation rules specified in components/page.js.twig.
 *
 * Target page: event
 */
$(document).ready(function() {

    // Apply select2 to locale field
    $('.js-select2').select2();

    $("#create-event").ufForm({
        validators: page.validators.create_event,
        msgTarget: $("#alerts-page"),
        binaryCheckboxes: true
    }).on("submitSuccess.ufForm", function() {
        // Reload the page on success
        window.location.reload();
    });
});
