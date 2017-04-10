/**
 * Page-specific Javascript file.  Should generally be included as a separate asset bundle in your page template.
 * example: {{ assets.js('js/pages/sign-in-or-register') | raw }}
 *
 * This script depends on widgets/events.js, uf-table.js, moment.js, handlebars-helpers.js
 *
 * Target page: /events
 */

$(document).ready(function() {
    // Set up table of events
    $("#widget-events").ufTable({
        dataUrl: site.uri.public + "/api/events"
    });

    // Bind creation button
    bindEventCreationButton($("#widget-events"));

    // Bind table buttons
    $("#widget-events").on("pagerComplete.ufTable", function () {
        bindEventButtons($(this));
    });
});
