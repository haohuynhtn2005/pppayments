<?php

class My_Plugin_Updater
{
    private $plugin_slug;
    private $remote_url;
    private $plugin_data;
    private $plugin_file;

    public function __construct($plugin_file, $remote_url)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->remote_url = $remote_url;

        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('site_transient_update_plugins', [$this, 'check_for_update']);
        add_action('upgrader_process_complete', [$this, 'clear_cache'], 10, 2);
    }

    public function plugin_info($res, $action, $args)
    {
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_slug) {
            return $res;
        }

        $remote = $this->get_remote_info();

        if (!$remote) {
            return $res;
        }

        $res = (object) [
            'name'        => $remote->name,
            'slug'        => $this->plugin_slug,
            'version'     => $remote->version,
            'author'      => $remote->author,
            'homepage'    => $remote->homepage,
            'sections'    => [
                'description' => $remote->description,
                'changelog'   => $remote->changelog,
            ],
            'download_link' => $remote->download_link,
        ];

        return $res;
    }

    public function check_for_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->get_remote_info();

        if ($remote && version_compare($remote->version, $this->get_current_version(), '>')) {
            $transient->response[$this->plugin_slug] = (object) [
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_slug,
                'new_version' => $remote->version,
                'package'     => $remote->download_link,
                'tested'      => $remote->tested,
                'requires'    => $remote->requires,
            ];
        }

        return $transient;
    }

    private function get_remote_info()
    {
        $response = wp_remote_get($this->remote_url, [
            'timeout' => 15,
            'body'    => [],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);

        return json_decode($body);
    }

    public function clear_cache($upgrader_object, $options)
    {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient('update_plugins');
        }
    }

    private function get_current_version()
    {
        if (!$this->plugin_data) {
            $this->plugin_data = get_plugin_data($this->plugin_file);
        }

        return $this->plugin_data['Version'];
    }
}
