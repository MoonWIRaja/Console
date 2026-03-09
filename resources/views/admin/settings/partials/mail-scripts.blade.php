<script>
    function getCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content');
    }

    function saveSettings() {
        return $.ajax({
            method: 'PATCH',
            url: '/admin/settings/mail',
            contentType: 'application/json',
            data: JSON.stringify({
                'mail:mailers:smtp:host': $('input[name="mail:mailers:smtp:host"]').val(),
                'mail:mailers:smtp:port': $('input[name="mail:mailers:smtp:port"]').val(),
                'mail:mailers:smtp:encryption': $('select[name="mail:mailers:smtp:encryption"]').val(),
                'mail:mailers:smtp:username': $('input[name="mail:mailers:smtp:username"]').val(),
                'mail:mailers:smtp:password': $('input[name="mail:mailers:smtp:password"]').val(),
                'mail:from:address': $('input[name="mail:from:address"]').val(),
                'mail:from:name': $('input[name="mail:from:name"]').val()
            }),
            headers: { 'X-CSRF-Token': getCsrfToken() }
        }).fail(function (jqXHR) {
            showErrorDialog(jqXHR, 'save');
        });
    }

    function testSettings() {
        swal({
            type: 'info',
            title: 'Test Mail Settings',
            text: 'Click "Test" to begin the test.',
            showCancelButton: true,
            confirmButtonText: 'Test',
            closeOnConfirm: false,
            showLoaderOnConfirm: true
        }, function () {
            $.ajax({
                method: 'POST',
                url: '/admin/settings/mail/test',
                headers: { 'X-CSRF-TOKEN': getCsrfToken() }
            }).fail(function (jqXHR) {
                showErrorDialog(jqXHR, 'test');
            }).done(function () {
                swal({
                    title: 'Success',
                    text: 'The test message was sent successfully.',
                    type: 'success'
                });
            });
        });
    }

    function saveAndTestSettings() {
        saveSettings().done(testSettings);
    }

    function showErrorDialog(jqXHR, verb) {
        console.error(jqXHR);
        var errorText = '';
        if (!jqXHR.responseJSON) {
            errorText = jqXHR.responseText;
        } else if (jqXHR.responseJSON.error) {
            errorText = jqXHR.responseJSON.error;
        } else if (jqXHR.responseJSON.errors) {
            $.each(jqXHR.responseJSON.errors, function (i, v) {
                if (v.detail) {
                    errorText += v.detail + ' ';
                }
            });
        }

        swal({
            title: 'Whoops!',
            text: 'An error occurred while attempting to ' + verb + ' mail settings: ' + errorText,
            type: 'error'
        });
    }

    $(document).ready(function () {
        $('#testButton').on('click', saveAndTestSettings);
        $('#saveButton').on('click', function () {
            saveSettings().done(function () {
                swal({
                    title: 'Success',
                    text: 'Mail settings have been updated successfully and the queue worker was restarted to apply these changes.',
                    type: 'success'
                });
            });
        });
    });
</script>
