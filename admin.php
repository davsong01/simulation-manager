<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function simmgr_admin_menu() {
    add_menu_page(
        'Simulation Manager',
        'Simulation Manager',
        'publish_posts',
        'simmgr',
        'simmgr_admin_page',
        'dashicons-hammer',
        20
    );
}

function simmgr_admin_page() {
    ?>
    <div class="wrap">
        <h1>Simulation Manager by David Oghi</h1>
        <p>Upload and manage HTML or ZIP simulation packages in <code>/simulations/</code>.</p>
        <button id="simmgr-add-button" class="button button-primary">Add Simulation</button><br>
        <div id="simmgr-notice" class="simmgr-notice" aria-live="polite"></div>

        <div id="simmgr-table-wrap" class="simmgr-table-wrap"></div>
    </div>

    <div id="simmgr-modal" class="simmgr-modal hidden">
        <div class="simmgr-modal-content">
            <h2 id="simmgr-modal-title">Add Simulation</h2>
            <div id="simmgr-modal-error" class="simmgr-modal-error" style="display:none; color: #d63638; margin-bottom: 10px;"></div>
            <form id="simmgr-form" method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="action_type" id="simmgr-action-type" value="create">
                <input type="hidden" name="record_id" id="simmgr-record-id" value="0">
                <input type="hidden" name="security" value="<?php echo esc_attr( wp_create_nonce( 'simmgr-admin' ) ); ?>">

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="simmgr-name">Name <span class="required">*</span></label></th>
                            <td><input id="simmgr-name" name="name" type="text" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="simmgr-folder-name">Folder Name</label></th>
                            <td><input id="simmgr-folder-name" name="folder_name" type="text" class="regular-text"></td>
                        </tr>
                        <tr id="simmgr-file-row">
                            <th scope="row"><label for="simmgr-file">File Upload <span class="required">*</span></label></th>
                            <td><input id="simmgr-file" name="simulation_file" type="file" accept=".html,.htm,.zip"></td>
                        </tr>
                        <tr>
                            <th scope="row">Rules</th>
                            <td>
                                <p class="description">Accepts <strong>.html</strong>, <strong>.htm</strong> or <strong>.zip</strong>. HTML files are saved directly; ZIP files are extracted into the folder. <small style="color: #d63638;">Ensure that zip files have an index file in the root.</small></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save Simulation</button>
                    <button type="button" class="button simmgr-close-button">Cancel</button>
                </p>
            </form>
        </div>
    </div>

    <div id="simmgr-delete-modal" class="simmgr-modal hidden">
        <div class="simmgr-modal-content">
            <h2>Delete Simulation</h2>
            <p>Are you sure you want to delete this simulation? This will remove the database record and the folder contents.</p>
            <div class="simmgr-modal-actions">
                <button type="button" class="button button-secondary simmgr-close-button">Cancel</button>
                <button type="button" class="button button-danger" id="simmgr-delete-confirm-button">Delete</button>
            </div>
        </div>
    </div>

    <div id="simmgr-folder-modal" class="simmgr-modal hidden">
        <div class="simmgr-modal-content">
            <h2>Folder Content</h2>
            <div id="simmgr-folder-tree"></div>
            <p><button type="button" class="button simmgr-close-button">Close</button></p>
        </div>
    </div>

    <script type="text/html" id="simmgr-row-template">
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Folder</th>
                    <th>Link</th>
                    <th>File Type</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="simmgr-table-body"></tbody>
        </table>
    </script>
    <?php
}
