/**
 * Page-specific Javascript file.  Should generally be included as a separate asset bundle in your page template.
 * example: {{ assets.js('js/pages/sign-in-or-register') | raw }}
 *
 * This script depends on validation rules specified in components/page.js.twig.
 *
 * Target page: create/event
 */
$(document).ready(function() {

    // Apply select2 to locale field
    $('.js-select2').select2();

    $("#create-event").ufForm({
        validators: page.validators.account_settings,
        msgTarget: $("#alerts-page"),
        binaryCheckboxes: true
    }).on("submitSuccess.ufForm", function() {
        // Reload the page on success
        window.location.reload();
    });
});
