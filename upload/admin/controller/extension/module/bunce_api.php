<?php

class ControllerExtensionModuleBunceApi extends Controller
{
    protected $error = [];

    public function index()
    {
        $this->load->language('extension/module/bunce_api');


        $this->document->addStyle('catalog/view/theme/bunceapi/stylesheet/custom.css');


        $this->document->setTitle($this->language->get('heading_title'));

        // Set success message if it exists
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);

        // Set error message if it exists
        $data['error_warning'] = isset($this->session->data['error_warning']) ? $this->session->data['error_warning'] : '';
        unset($this->session->data['error_warning']);


        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/bunce_api', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['save'] = $this->url->link('extension/module/bunce_api/save', 'user_token=' . $this->session->data['user_token'], true);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $this->load->model('setting/setting');
        $settings = $this->model_setting_setting->getSetting('bunce_api');

        // Load existing settings
        $data['bunce_api_event_id'] = $settings['bunce_api_event_id'] ?? '';
        $data['bunce_api_event_id_2'] = $settings['bunce_api_event_id_2'] ?? '';
        $data['bunce_api_event_id_3'] = $settings['bunce_api_event_id_3'] ?? '';
        $data['bunce_api_event_id_4'] = $settings['bunce_api_event_id_4'] ?? '';
        $data['bunce_api_abandoned_cart_duration'] = $settings['bunce_api_abandoned_cart_duration'] ?? 0;
        $data['bunce_api_key'] = $settings['bunce_api_key'] ?? '';
        $data['bunce_api_status'] = $settings['bunce_api_status'] ?? '';

        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/bunce_api', $data));
    }

    public function save()
    {
        $this->load->language('extension/module/bunce_api');

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $payload = [
                'bunce_api_event_id' => $this->request->post['bunce_api_event_id'] ?? '',
                'bunce_api_event_id_2' => $this->request->post['bunce_api_event_id_2'] ?? '',
                'bunce_api_event_id_3' => $this->request->post['bunce_api_event_id_3'] ?? '',
                'bunce_api_event_id_4' => $this->request->post['bunce_api_event_id_4'] ?? '',
                'bunce_api_abandoned_cart_duration' => $this->request->post['bunce_api_abandoned_cart_duration'] ?? 0,
                'bunce_api_key' => $this->request->post['bunce_api_key'] ?? '',
                'bunce_api_status' => $this->request->post['bunce_api_status'] ?? ''
            ];

            $this->session->data['success'] = $this->language->get('text_success');

            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('bunce_api', $payload);
        } else {
            $this->session->data['error_warning'] = $this->language->get('error_invalid_data');
        }

        $this->index();
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/bunce_api')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['bunce_api_key'])) {
            $this->error['bunce_api_key'] = $this->language->get('error_api_key');
        }

        if (!is_numeric($this->request->post['bunce_api_abandoned_cart_duration']) || $this->request->post['bunce_api_abandoned_cart_duration'] <= 0) {
            $this->error['bunce_api_abandoned_cart_duration'] = $this->language->get('error_abandoned_cart_duration');
        }

        if ($this->error) {
            $this->session->data['error_warning'] = $this->error['warning'] ?? '';
            return false;
        }

        return true;
    }

    public function triggerCheckoutAccessAfterEvent()
    {
        $this->sendEvent('Checkout page accessed - after', ['event_id' => $this->config->get('bunce_api_event_id')]);
    }

    public function triggerViewProductEvent($product_id)
    {
        $this->sendEvent('Product viewed', ['product_id' => $product_id, 'event_id' => $this->config->get('bunce_api_event_id_2')]);
    }

    public function triggerProductAddedToCartEvent($product_id)
    {
        $this->sendEvent('Product added to cart', ['product_id' => $product_id, 'event_id' => $this->config->get('bunce_api_event_id_3')]);
    }

    private function sendEvent($message, $additional_payload = [])
    {
        $api_key = $this->config->get('bunce_api_key');

        if ($api_key) {
            $url = 'https://test.api.bunce.so/v1/events/trigger';
            $payload = array_merge(['message' => $message], $additional_payload);

            $data = [
                'payload' => $payload
            ];

            $options = [
                'header' => [
                    'X-Authorization: ' . $api_key,
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($data))
                ]
            ];

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $options['header']);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                $this->log->write('Bunce API Error: ' . $err);
            } else {
                $this->log->write('Bunce API Response: ' . $response);
            }
        } else {
            $this->log->write('Bunce API Error: Missing API key');
        }
    }

    public function install()
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('bunce_api', [
            'bunce_api_status' => 0,
            'bunce_api_event_id' => '',
            'bunce_api_event_id_2' => '',
            'bunce_api_event_id_3' => '',
            'bunce_api_event_id_4' => '',
            'bunce_api_abandoned_cart_duration' => 0,
            'bunce_api_key' => ''
        ]);

        $this->load->model('setting/event');
        $this->model_setting_event->addEvent('extension_bunce_api_checkout_access_after', 'catalog/controller/checkout/checkout/after', 'extension/module/bunce_api/triggerCheckoutAccessAfterEvent');
        $this->model_setting_event->addEvent('extension_bunce_api_view_product', 'catalog/controller/product/product/after', 'extension/module/bunce_api/triggerViewProductEvent');
        $this->model_setting_event->addEvent('extension_bunce_api_product_added_to_cart', 'catalog/controller/checkout/cart/add/before', 'extension/module/bunce_api/triggerProductAddedToCartEvent');
    }

    public function uninstall()
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('bunce_api');

        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('extension_bunce_api_checkout_access_after');
        $this->model_setting_event->deleteEventByCode('extension_bunce_api_view_product');
        $this->model_setting_event->deleteEventByCode('extension_bunce_api_product_added_to_cart');

        $this->log->write('Bunce API module uninstalled successfully.');
    }
}
