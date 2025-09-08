<?php
/**
 * File Status Table Rows Template
 * 
 * Template for generating file status table rows with processing status,
 * matching the structure from FilesFilters.php direct HTML generation.
 * 
 * @package DataMachine
 * @subpackage Core\Admin\Pages\Pipelines\Templates\Modal
 * @since 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract files from context
$files = $context['files'] ?? [];

// Generate table rows
foreach ($files as $file): 
    $status_class = $file['is_processed'] ? 'processed' : 'pending';
    $status_icon = $file['is_processed'] ? 'dashicons-yes-alt' : 'dashicons-clock';
    $status_color = $file['is_processed'] ? '#46b450' : '#ffb900';
?>
    <tr class="dm-file-row dm-file-status-<?php echo esc_attr($status_class); ?>">
        <td class="dm-file-name-col">
            <span class="dashicons dashicons-media-default"></span>
            <span class="dm-file-name"><?php echo esc_html($file['filename']); ?></span>
        </td>
        <td class="dm-file-size-col"><?php echo esc_html($file['size_formatted']); ?></td>
        <td class="dm-file-status-col">
            <span class="dashicons <?php echo esc_attr($status_icon); ?> dm-file-status-icon" data-status="<?php echo esc_attr($file['is_processed'] ? 'processed' : 'pending'); ?>"></span>
            <span class="dm-file-status"><?php echo esc_html($file['status']); ?></span>
        </td>
        <td class="dm-file-date-col"><?php echo esc_html($file['modified_formatted']); ?></td>
        <td class="dm-file-actions-col">
            <button type="button" class="button button-small dm-delete-file" data-filename="<?php echo esc_attr($file['filename']); ?>" title="<?php echo esc_attr(__('Delete file', 'data-machine')); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </td>
    </tr>
<?php endforeach; ?>