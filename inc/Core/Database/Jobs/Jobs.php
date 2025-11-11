<?php
/**
 * Jobs Database Operations - Job lifecycle management with engine data storage
 *
 * @package DataMachine
 * @subpackage Core\Database\Jobs
 */

namespace DataMachine\Core\Database\Jobs;

if (!defined('ABSPATH')) {
	exit;
}

class Jobs {

    private $table_name;
    private $operations;
    private $status;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'dm_jobs';
        
        $this->operations = new JobsOperations();
        $this->status = new JobsStatus();
    }


    public function create_job(array $job_data): int|false {
        return $this->operations->create_job($job_data);
    }


    public function get_jobs_count(): int {
        return $this->operations->get_jobs_count();
    }

    public function get_jobs_for_list_table(array $args): array {
        return $this->operations->get_jobs_for_list_table($args);
    }

    public function start_job(int $job_id, string $status = 'processing'): bool {
        return $this->status->start_job($job_id, $status);
    }

    public function complete_job(int $job_id, string $status): bool {
        return $this->status->complete_job($job_id, $status);
    }

    public function update_job_status(int $job_id, string $status): bool {
        return $this->status->update_job_status($job_id, $status);
    }

    public function get_jobs_for_pipeline(int $pipeline_id): array {
        return $this->operations->get_jobs_for_pipeline($pipeline_id);
    }

    public function get_jobs_for_flow(int $flow_id): array {
        return $this->operations->get_jobs_for_flow($flow_id);
    }

    public function delete_jobs(array $criteria = []): int|false {
        return $this->operations->delete_jobs($criteria);
    }

    public function store_engine_data(int $job_id, array $data): bool {
        return $this->operations->store_engine_data($job_id, $data);
    }

    public function retrieve_engine_data(int $job_id): array {
        return $this->operations->retrieve_engine_data($job_id);
    }

    public function get_job(int $job_id): ?object {
        return $this->operations->get_job($job_id);
    }


    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dm_jobs';
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $sql = "CREATE TABLE $table_name (
            job_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            pipeline_id bigint(20) unsigned NOT NULL,
            flow_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL,
            engine_data longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (job_id),
            KEY status (status),
            KEY pipeline_id (pipeline_id),
            KEY flow_id (flow_id)
        ) $charset_collate;";

        dbDelta( $sql );

        do_action('datamachine_log', 'debug', 'Created jobs database table with pipeline+flow architecture', [
            'table_name' => $table_name,
            'action' => 'create_table'
        ]);
    }

}