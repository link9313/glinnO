/**
 * Events widget.  Sets up dropdowns, modals, etc for a table of events.
 */

/**
 * Set up the form in a modal after being successfully attached to the body.
 */
function attachEventForm() {
    $("body").on('renderSuccess.ufModal', function (data) {
        var modal = $(this).ufModal('getModal');
        var form = modal.find('.js-form');

        // Set up any widgets inside the modal
        form.find(".js-select2").select2({
            width: '100%'
        });

        // Set up the form for submission
        form.ufForm({
            validators: page.validators,
            binaryCheckboxes: true
        }).on("submitSuccess.ufForm", function() {
            // Reload page on success
            window.location.reload();
        });
    });
}

/**
 * Update event field(s)
 */
function updateEvent(eventName, fieldName, fieldValue) {
	var data = {
        'value': fieldValue
    };

    data[site.csrf.keys.name] = site.csrf.name;
    data[site.csrf.keys.value] = site.csrf.value;

    var url = site.uri.public + '/api/events/e/' + id + '/' + fieldName;

    return $.ajax({
        type: "PUT",
        url: url,
        data: data
	}).fail(function (response) {
        // Error messages
        if ((typeof site !== "undefined") && site.debug.ajax && response.responseText) {
            document.write(response.responseText);
            document.close();
        } else {
            console.log("Error (" + response.status + "): " + response.responseText );
        }

        return response;
    }).always(function (response) {
        window.location.reload();
    });
}

/**
 * Link event action buttons, for example in a table or on a specific event's page.
 */
 function bindEventButtons(el) {

    /**
     * Buttons that launch a modal dialog
     */
    // Edit general event details button
    el.find('.js-event-edit').click(function() {
        $("body").ufModal({
            sourceUrl: site.uri.public + "/modals/events/edit",
            ajaxParams: {
                name: $(this).data('name')
            },
            msgTarget: $("#alerts-page")
        });

        attachEventForm();
    });

    // Delete event button
    el.find('.js-event-delete').click(function() {
        $("body").ufModal({
            sourceUrl: site.uri.public + "/modals/events/confirm-delete",
            ajaxParams: {
                name: $(this).data('id')
            },
            msgTarget: $("#alerts-page")
        });

        $("body").on('renderSuccess.ufModal', function (data) {
            var modal = $(this).ufModal('getModal');
            var form = modal.find('.js-form');

            form.ufForm()
            .on("submitSuccess.ufForm", function() {
                // Reload page on success
                window.location.reload();
            });
        });
    });

    /**
     * Direct action buttons
     */
    el.find('.js-event-activate').click(function() {
        var btn = $(this);
        updateUser(btn.data('id'), 'flag_verified', '1');
    });

    el.find('.js-event-enable').click(function () {
        var btn = $(this);
        updateUser(btn.data('id'), 'flag_enabled', '1');
    });

    el.find('.js-event-disable').click(function () {
        var btn = $(this);
        updateUser(btn.data('id'), 'flag_enabled', '0');
    });
}

function bindEventCreationButton(el) {
    // Link create button
    el.find('.js-event-create').click(function() {
        $("body").ufModal({
            sourceUrl: site.uri.public + "/modals/events/create",
            msgTarget: $("#alerts-page")
        });

        attachEventForm();
    });
};
