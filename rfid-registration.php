<?php
/**
 * Plugin Name: RFID Registration
 * Description: A plugin to register RFID cards manually for vending machine users.
 * Version: 1.2
 * Author: Sabit Shahriar
 */

// Add a menu item in the WordPress dashboard
function rfid_registration_menu() {
    add_menu_page('RFID Registration', 'RFID Registration', 'manage_options', 'rfid-registration', 'rfid_registration_form');
    add_submenu_page('rfid-registration', 'View RFID Cards', 'View Registered Cards', 'manage_options', 'view-rfid-cards', 'view_rfid_cards');
}
add_action('admin_menu', 'rfid_registration_menu');

// Create the form for registering RFID cards
function rfid_registration_form() {
    ?>
    <div class="wrap">
        <h2>Register RFID Card</h2>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">User ID:</th>
                    <td><input type="text" name="user_id" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">RFID Card ID:</th>
                    <td><input type="text" name="rfid_id" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Balance:</th>
                    <td><input type="number" step="0.01" name="balance" required /></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" class="button-primary" value="Register RFID" />
            </p>
        </form>
    </div>
    <?php

    if (isset($_POST['submit'])) {
        global $wpdb;
        $user_id = sanitize_text_field($_POST['user_id']);
        $rfid_id = sanitize_text_field($_POST['rfid_id']);
        $balance = sanitize_text_field($_POST['balance']);

        // Insert the RFID card details into the database
        $table_name = $wpdb->prefix . 'rfid_users';
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'rfid_id' => $rfid_id,
                'balance' => $balance
            )
        );

        echo "<p>RFID card registered successfully!</p>";
    }
}

// Function to display and manage registered RFID cards in a table
function view_rfid_cards() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rfid_users';

    // Check if the "Export CSV" button is clicked
    if (isset($_POST['export_csv'])) {
        $rfid_data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=rfid-cards.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('User ID', 'RFID Card ID', 'Balance'));

        foreach ($rfid_data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    // Handling editing and deleting
    if (isset($_GET['edit'])) {
        $id = intval($_GET['edit']);
        $rfid_card = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

        if ($rfid_card) {
            // Show edit form
            if (isset($_POST['update'])) {
                $new_user_id = sanitize_text_field($_POST['user_id']);
                $new_rfid_id = sanitize_text_field($_POST['rfid_id']);
                $new_balance = sanitize_text_field($_POST['balance']);

                // Update RFID card details in the database
                $wpdb->update($table_name, array(
                    'user_id' => $new_user_id,
                    'rfid_id' => $new_rfid_id,
                    'balance' => $new_balance,
                ), array('id' => $id));

                echo "<p>RFID card updated successfully!</p>";
            }

            // Show the edit form
            ?>
            <h2>Edit RFID Card</h2>
            <form method="post">
                <table>
                    <tr><td>User ID:</td><td><input type="text" name="user_id" value="<?php echo esc_attr($rfid_card->user_id); ?>" /></td></tr>
                    <tr><td>RFID Card ID:</td><td><input type="text" name="rfid_id" value="<?php echo esc_attr($rfid_card->rfid_id); ?>" /></td></tr>
                    <tr><td>Balance:</td><td><input type="number" step="0.01" name="balance" value="<?php echo esc_attr($rfid_card->balance); ?>" /></td></tr>
                </table>
                <input type="submit" name="update" class="button-primary" value="Update" />
            </form>
            <?php
            return;
        }
    }

    // Check if an RFID card is being deleted
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $wpdb->delete($table_name, array('id' => $id));
        echo "<p>RFID card deleted successfully!</p>";
    }

    // Get sorting parameters
    $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'id';
    $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? $_GET['order'] : 'ASC';

    // Get filtering parameters
    $filter_user_id = isset($_GET['filter_user_id']) ? sanitize_text_field($_GET['filter_user_id']) : '';
    $filter_rfid_id = isset($_GET['filter_rfid_id']) ? sanitize_text_field($_GET['filter_rfid_id']) : '';

    // Build SQL query with optional filtering
    $where = 'WHERE 1=1';
    if ($filter_user_id) {
        $where .= $wpdb->prepare(' AND user_id LIKE %s', '%' . $filter_user_id . '%');
    }
    if ($filter_rfid_id) {
        $where .= $wpdb->prepare(' AND rfid_id LIKE %s', '%' . $filter_rfid_id . '%');
    }

    // Fetch RFID data with sorting and filtering
    $rfid_data = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY $orderby $order");

    ?>
    <div class="wrap">
        <h2>Registered RFID Cards</h2>

        <!-- Export CSV Button -->
        <form method="post">
            <input type="submit" name="export_csv" class="button" value="Export to CSV" />
        </form>

        <!-- Filtering Form -->
        <form method="get">
            <input type="hidden" name="page" value="view-rfid-cards" />
            <input type="text" name="filter_user_id" placeholder="Filter by User ID" value="<?php echo esc_attr($filter_user_id); ?>" />
            <input type="text" name="filter_rfid_id" placeholder="Filter by RFID ID" value="<?php echo esc_attr($filter_rfid_id); ?>" />
            <input type="submit" value="Filter" class="button" />
            <a href="<?php echo admin_url('admin.php?page=view-rfid-cards'); ?>" class="button">Reset Filters</a>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><a href="?page=view-rfid-cards&orderby=user_id&order=<?php echo $order === 'ASC' ? 'DESC' : 'ASC'; ?>">User ID</a></th>
                    <th scope="col"><a href="?page=view-rfid-cards&orderby=rfid_id&order=<?php echo $order === 'ASC' ? 'DESC' : 'ASC'; ?>">RFID Card ID</a></th>
                    <th scope="col"><a href="?page=view-rfid-cards&orderby=balance&order=<?php echo $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Balance</a></th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($rfid_data) {
                    foreach ($rfid_data as $row) {
                        echo "<tr>
                            <td>{$row->user_id}</td>
                            <td>{$row->rfid_id}</td>
                            <td>{$row->balance}</td>
                            <td><a href='?page=view-rfid-cards&edit={$row->id}' class='button'>Edit</a> | 
                                <a href='?page=view-rfid-cards&delete={$row->id}' class='button' onclick='return confirm(\"Are you sure?\");'>Delete</a></td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>No RFID cards found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Function to create the table for RFID users when the plugin is activated
function create_rfid_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rfid_users';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id varchar(50) NOT NULL,
        rfid_id varchar(50) NOT NULL,
        balance float DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_rfid_table');
