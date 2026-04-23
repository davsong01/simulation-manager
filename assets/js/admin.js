(function($){
    var activeDeleteId = 0;
    var activeFolderName = '';

    function showNotice(message, type) {
        var notice = $('#simmgr-notice');
        notice.text(message).removeClass('simmgr-success simmgr-error');
        notice.addClass(type === 'success' ? 'simmgr-success' : 'simmgr-error');
    }

    function hideNotice() {
        $('#simmgr-notice').text('').removeClass('simmgr-success simmgr-error');
    }

    function toggleModal(modal, state) {
        if (state) {
            modal.removeClass('hidden');
        } else {
            modal.addClass('hidden');
        }
    }

    function buildTable(rows) {
        var template = $('#simmgr-row-template').html();
        $('#simmgr-table-wrap').html(template);
        var body = $('#simmgr-table-body');
        if (!rows.length) {
            body.append('<tr><td colspan="6">No simulations found.</td></tr>');
            return;
        }
        rows.forEach(function(row){
            var fileType = row.file_type;
            var viewFolder = '<button class="button button-primary button-small simmgr-view-folder" data-folder="' + row.folder_name + '">View Folder Content</button>';
            var editButton = '<button class="button button-secondary button-small simmgr-edit-button" data-id="' + row.id + '" data-name="' + encodeURIComponent(row.name) + '" data-folder="' + encodeURIComponent(row.folder_name) + '">Edit</button>';
            var deleteButton = '<button class="button button-danger button-small simmgr-delete-button" data-id="' + row.id + '">Delete</button>';
            var linkDisplay = '<a href="' + row.link + '" target="_blank" rel="noopener">' + row.link + '</a> <button class="simmgr-copy-link" data-link="' + row.link + '" title="Copy Link"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16 1H4C2.9 1 2 1.9 2 3V17H4V3H16V1ZM19 5H8C6.9 5 6 5.9 6 7V21C6 22.1 6.9 23 8 23H19C20.1 23 21 22.1 21 21V7C21 5.9 20.1 5 19 5ZM19 21H8V7H19V21Z" fill="currentColor"/></svg></button>';
            body.append('<tr>' +
                '<td>' + $('<div>').text(row.name).html() + '</td>' +
                '<td>' + $('<div>').text(row.folder_name).html() + '</td>' +
                '<td>' + linkDisplay + '</td>' +
                '<td>' + $('<div>').text(fileType).html() + '</td>' +
                '<td>' + $('<div>').text(row.created_at).html() + '</td>' +
                '<td><div class="simmgr-actions">' + editButton + viewFolder + deleteButton + '</div></td>' +
                '</tr>');
        });
    }

    function loadSimulations() {
        $.post(simmgrData.ajax_url, {
            action: 'simmgr_load_simulations',
            security: simmgrData.nonce
        }, function(response){
            if ( response.success ) {
                buildTable(response.data.rows);
            } else {
                showNotice(response.data || 'Unable to load simulations.', 'error');
            }
        });
    }

    function resetForm() {
        $('#simmgr-form')[0].reset();
        $('#simmgr-action-type').val('create');
        $('#simmgr-record-id').val('0');
        $('#simmgr-modal-title').text('Add Simulation');
        $('#simmgr-file-row').show();
    }

    function renderTree(items, container) {
        var list = $('<ul></ul>');
        items.forEach(function(item){
            var entry = $('<li></li>');
            if (item.type === 'folder') {
                entry.html('<span class="folder">' + item.name + '</span>');
                entry.append(renderTree(item.children, entry));
            } else {
                entry.html('<span class="file">' + item.name + '</span>');
            }
            list.append(entry);
        });
        return list;
    }

    function bindEvents() {
        $('#simmgr-add-button').on('click', function(){
            resetForm();
            toggleModal($('#simmgr-modal'), true);
        });

        // Bind the Cancel/Close buttons to hide the modals
        $(document).on('click', '.simmgr-close-button', function() {
            toggleModal($(this).closest('.simmgr-modal'), false);
            hideNotice();
        });

        $(document).on('click', '.simmgr-modal', function(e){
        if (e.target === this) {
            toggleModal($(this), false);
            hideNotice();
        }
        });

        $(document).on('click', '.simmgr-delete-button', function(){
            activeDeleteId = $(this).data('id');
            toggleModal($('#simmgr-delete-modal'), true);
        });

        $(document).on('click', '.simmgr-edit-button', function(){
            var id = $(this).data('id');
            var name = decodeURIComponent($(this).data('name'));
            var folder = decodeURIComponent($(this).data('folder'));
            $('#simmgr-action-type').val('update');
            $('#simmgr-record-id').val(id);
            $('#simmgr-name').val(name);
            $('#simmgr-folder-name').val(folder);
            $('#simmgr-modal-title').text('Edit Simulation');
            $('#simmgr-file-row').show();
            toggleModal($('#simmgr-modal'), true);
        });

        $(document).on('click', '.simmgr-view-folder', function(){
            activeFolderName = $(this).data('folder');
            $.post(simmgrData.ajax_url, {
                action: 'simmgr_get_folder_contents',
                security: simmgrData.nonce,
                folder_name: activeFolderName
            }, function(response){
                if ( response.success ) {
                    var tree = renderTree(response.data.contents, $('#simmgr-folder-tree'));
                    $('#simmgr-folder-tree').empty().append(tree);
                    toggleModal($('#simmgr-folder-modal'), true);
                } else {
                    showNotice(response.data || 'Unable to display folder content.', 'error');
                }
            });
        });

        $(document).on('click', '.simmgr-copy-link', function(){
            var link = $(this).data('link');
            navigator.clipboard.writeText(link).then(function(){
                alert('Link copied to clipboard!');
            }).catch(function(err){
                console.error('Failed to copy: ', err);
            });
        });

        $('#simmgr-delete-confirm-button').on('click', function(){
            var $btn = $(this);
            toggleLoading($btn, true); // Start Spinner

            $.post(simmgrData.ajax_url, {
                action: 'simmgr_delete_simulation',
                security: simmgrData.nonce,
                record_id: activeDeleteId
            }, function(response){
                toggleLoading($btn, false); // Stop Spinner
                if ( response.success ) {
                    loadSimulations();
                    toggleModal($('#simmgr-delete-modal'), false);
                    showNotice(response.data, 'success');
                } else {
                    showNotice(response.data || 'Unable to delete.', 'error');
                }
            });
        });

        $('#simmgr-form').on('submit', function(e){
            e.preventDefault();
            var $btn = $(this).find('button[type="submit"]');
            hideNotice();
            $('#simmgr-modal-error').hide().text('');
            
            var formData = new FormData(this);
            formData.append('action', 'simmgr_save_simulation');
            formData.append('security', simmgrData.nonce);

            toggleLoading($btn, true); // Start Spinner

            $.ajax({
                url: simmgrData.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response){
                    toggleLoading($btn, false); // Stop Spinner
                    if ( response.success ) {
                        loadSimulations();
                        toggleModal($('#simmgr-modal'), false);
                        showNotice(response.data, 'success');
                    } else {
                        $('#simmgr-modal-error').text(response.data || 'Unable to save simulation.').show();
                    }
                },
                error: function(xhr, status, error){
                    toggleLoading($btn, false); // Stop Spinner
                    $('#simmgr-modal-error').text('Upload failed: ' + error).show();
                }
            });
        });
        // $('#simmgr-form').on('submit', function(e){
        //     e.preventDefault();
        //     hideNotice();
        //     $('#simmgr-modal-error').hide().text('');
        //     var formData = new FormData(this);
        //     formData.append('action', 'simmgr_save_simulation');
        //     formData.append('security', simmgrData.nonce);

        //     $.ajax({
        //         url: simmgrData.ajax_url,
        //         type: 'POST',
        //         data: formData,
        //         processData: false,
        //         contentType: false,
        //         success: function(response){
        //             if ( response.success ) {
        //                 loadSimulations();
        //                 toggleModal($('#simmgr-modal'), false);
        //                 showNotice(response.data, 'success');
        //             } else {
        //                 $('#simmgr-modal-error').text(response.data || 'Unable to save simulation.').show();
        //             }
        //         },
        //         error: function(xhr, status, error){
        //             $('#simmgr-modal-error').text('Upload failed: ' + error).show();
        //         }
        //     });
        // });
    }

    function toggleLoading(btn, isLoading) {
        if (isLoading) {
            btn.addClass('is-loading').append('<span class="simmgr-spinner"></span>');
        } else {
            btn.removeClass('is-loading').find('.simmgr-spinner').remove();
        }
    }

    $(function(){
        bindEvents();
        loadSimulations();
    });
})(jQuery);
