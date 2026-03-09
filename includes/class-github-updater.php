<?php
/**
 * Alchemy GitHub Updater
 * Handles automatic updates directly from the GitHub repository.
 */

if (!defined('ABSPATH')) exit;

class Alchemy_GitHub_Updater {

    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $username;
    private $repo;
    private $github_response;

    public function __construct($file) {
        $this->file = $file;
        $this->username = 'jaredrlawson';
        $this->repo     = 'alchemy-booking-system';

        add_action('admin_init', [$this, 'set_plugin_properties']);
        add_filter('site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'fix_source_folder'], 10, 4);
        add_action('upgrader_process_complete', [$this, 'after_install'], 10, 2);
    }

    public function fix_source_folder($source, $remote_source, $upgrader, $hook_extra) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
            return $source;
        }

        $folder_name = dirname($this->basename);
        $new_source = trailingslashit($remote_source) . $folder_name . '/';
        
        if (rename($source, $new_source)) {
            return $new_source;
        }

        return $source;
    }

    public function set_plugin_properties() {
        $this->plugin   = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active   = is_plugin_active($this->basename);
    }

    private function get_repository_info() {
        if (is_null($this->github_response)) {
            $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";
            $response = wp_remote_get($url);

            if (is_wp_error($response)) return false;

            $this->github_response = json_decode(wp_remote_retrieve_body($response));
        }
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) return $transient;

        $this->get_repository_info();
        if (!$this->github_response) return $transient;

        $new_version = ltrim($this->github_response->tag_name, 'v');

        if (version_compare($this->plugin['Version'], $new_version, '<')) {
            $obj = new stdClass();
            $obj->slug = $this->basename;
            $obj->new_version = $new_version;
            $obj->url = $this->plugin['PluginURI'];
            $obj->package = $this->github_response->zipball_url;

            $transient->response[$this->basename] = $obj;
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') return $result;
        if ($args->slug !== $this->basename) return $result;

        $this->get_repository_info();
        if (!$this->github_response) return $result;

        $res = new stdClass();
        $res->name = $this->plugin['Name'];
        $res->slug = $this->basename;
        $res->version = ltrim($this->github_response->tag_name, 'v');
        $res->author = $this->plugin['AuthorName'];
        $res->homepage = $this->plugin['PluginURI'];
        $res->download_link = $this->github_response->zipball_url;
        $res->sections = [
            'description' => $this->plugin['Description'],
            'changelog'   => $this->github_response->body
        ];

        return $res;
    }

    public function after_install($upgrader_object, $options) {
        if ($options['action'] == 'update' && $options['type'] == 'plugin') {
            delete_site_transient('update_plugins');
        }
    }
}
