<?php

namespace Opencart\Admin\Controller\Extension\BunceApi\Module;

class BunceApi extends \Opencart\System\Engine\Controller
{
    public function index(): void
    {
        $this->load->language('extension/bunceapi/module/bunce_api');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/bunceapi/module/bunce_api', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/bunceapi/module/bunce_api.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

        $this->load->model('setting/setting');

        // Load existing settings
        $settings = $this->model_setting_setting->getSetting('bunce_api');

        $data['bunce_api_event_id'] = $settings['bunce_api_event_id'] ?? '';
        $data['bunce_api_key'] = $settings['bunce_api_key'] ?? '';
        $data['bunce_api_status'] = $settings['bunce_api_status'] ?? '';

        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/bunceapi/module/bunce_api', $data));
    }

    public function save(): void
    {
        $this->load->language('extension/bunceapi/module/bunce_api');

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $payload = [
                'bunce_api_event_id' => $this->request->post['bunce_api_event_id'] ?? '',
                'bunce_api_key' => $this->request->post['bunce_api_key'] ?? '',
                'bunce_api_status' => $this->request->post['bunce_api_status'] ?? ''
            ];

            $this->session->data['success'] = $this->language->get('text_success');

            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('bunce_api', $payload);
        }

        $this->index();
    }

    public function install(): void
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('bunce_api', [
            'bunce_api_status' => 0,
            'bunce_api_event_id' => '',
            'bunce_api_key' => ''
        ]);

        $this->load->model('setting/event');
        $this->model_setting_event->addEvent('extension_bunce_api_checkout_access_before', 'catalog/controller/checkout/checkout/before', 'extension/bunceapi/module/bunce_api.triggerCheckoutAccessBeforeEvent');
        $this->model_setting_event->addEvent('extension_bunce_api_checkout_access_after', 'catalog/controller/checkout/checkout/after', 'extension/bunceapi/module/bunce_api.triggerCheckoutAccessAfterEvent');

        // Optionally create a custom table for API logs
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "bunce_api_log` (
                `log_id` int(11) NOT NULL AUTO_INCREMENT,
                `event` varchar(255) NOT NULL,
                `response` text NOT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`log_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }

    public function uninstall(): void
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('bunce_api');

        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('extension_bunce_api_checkout_access_before');
        $this->model_setting_event->deleteEventByCode('extension_bunce_api_checkout_access_after');

        // Optionally drop the custom table for API logs
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "bunce_api_log`");
    }


    protected function validate(): bool
    {
        // Example permission check
        if (!$this->user->hasPermission('modify', 'extension/bunceapi/module/bunce_api')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}
