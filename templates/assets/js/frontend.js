jQuery(document).ready(function($) {
    $("body").on("click",".some-btn", function() {

        var data = {
            text: 'some text',
            value: 'some value'
        }

        $.ajax({
            url: admin.ajaxurl,
            data: {
                'action': 'get_list_items',
                'data': data,
                'ajax_nonce': admin.ajax_nonce
            },
            type: 'POST',
            dataType: 'json',
            success: function (response) {
                if (response.status == 'true') {
                    $('#delete-selected-curations').attr("disabled", false);
                }
            }
        });
    });
});